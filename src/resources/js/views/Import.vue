<template>
    <div>
        <h2 class="text-2xl font-semibold text-gray-800 mb-6">
            Import Contacts
        </h2>

        <div class="grid grid-cols-3 gap-4 mb-6">
            <ImportInputCard
                :debug-loading="debugLoading"
                :debug-message="debugMessage"
                :error="error"
                :file="file"
                :loading="loading"
                :progress="progress"
                :progress-steps="progressSteps"
                :show-progress="showProgress"
                @file-change="onFileChange"
                @truncate-contacts="truncateContacts"
                @upload="upload"
            />

            <ImportSummaryCard v-if="result" :result="result" />

            <ImportPerformanceCard v-if="result" :result="result" />
        </div>

        <div class="mt-8">
            <PaginatedTable
                :current-page="rejectedCurrentPage"
                :empty-message="'No rejected records found.'"
                :from="rejectedFrom"
                :items="rejectedRecords"
                :last-page="rejectedLastPage"
                :loading="rejectedLoading"
                :loading-message="'Loading rejected records...'"
                :search="rejectedSearch"
                :search-placeholder="'Search rejected records...'"
                :to="rejectedTo"
                :total="rejectedTotal"
                @page-change="goToRejectedPage"
                @update:search="rejectedSearch = $event"
            >
                <template #title>
                    <h3 class="text-xl font-semibold text-gray-800">
                        Rejected Records
                    </h3>
                </template>

                <template #header>
                    <th class="text-left px-5 py-3 font-medium text-gray-500">
                        Name
                    </th>
                    <th class="text-left px-5 py-3 font-medium text-gray-500">
                        Email
                    </th>
                    <th class="text-left px-5 py-3 font-medium text-gray-500">
                        Reason
                    </th>
                </template>

                <template #row="{ item: record }">
                    <td class="px-5 py-3 font-medium text-gray-800">
                        {{ record.first_name }} {{ record.last_name }}
                    </td>
                    <td class="px-5 py-3 text-gray-600">
                        {{ record.email }}
                    </td>
                    <td class="px-5 py-3 text-red-600">
                        {{ record.failure_reason }}
                    </td>
                </template>
            </PaginatedTable>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";
import axios from "axios";
import ImportInputCard from "../components/ImportInputCard.vue";
import ImportPerformanceCard from "../components/ImportPerformanceCard.vue";
import ImportSummaryCard from "../components/ImportSummaryCard.vue";
import PaginatedTable from "../components/PaginatedTable.vue";

const file = ref(null);
const loading = ref(false);
const result = ref(null);
const error = ref(null);
const debugLoading = ref(false);
const debugMessage = ref(null);

const showProgress = ref(false);
const progress = ref(null);
const STEPS = [
    { key: "parse", label: "XML parsed" },
    { key: "load", label: "Loaded into staging" },
    { key: "dedupe_input", label: "Deduplicated within file" },
    { key: "dedupe_db", label: "Checked against database" },
    { key: "insert", label: "Contacts inserted" },
];
const progressSteps = ref(STEPS.map((s) => ({ ...s, done: false })));

const rejectedRecords = ref([]);
const rejectedLoading = ref(false);
const rejectedSearch = ref("");
const rejectedCurrentPage = ref(1);
const rejectedLastPage = ref(1);
const rejectedTotal = ref(0);
const rejectedFrom = ref(0);
const rejectedTo = ref(0);

let rejectedSearchDebounceTimer = null;

function onFileChange(e) {
    file.value = e.target.files[0] ?? null;
    result.value = null;
    error.value = null;
    debugMessage.value = null;
    showProgress.value = false;
    progress.value = null;
    progressSteps.value = STEPS.map((s) => ({ ...s, done: false }));
}

async function upload() {
    if (!file.value) return;

    const form = new FormData();
    form.append("file", file.value);

    loading.value = true;
    result.value = null;
    error.value = null;
    debugMessage.value = null;
    showProgress.value = true;
    progress.value = 0;
    progressSteps.value = STEPS.map((s) => ({ ...s, done: false }));

    try {
        const response = await fetch("/api/import/stream", {
            method: "POST",
            body: form,
            headers: { Accept: "application/x-ndjson" },
        });

        if (!response.ok) {
            const json = await response.json().catch(() => ({}));
            throw new Error(json.message ?? "Import failed.");
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = "";

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            buffer += decoder.decode(value, { stream: true });
            const lines = buffer.split("\n");
            buffer = lines.pop(); // keep the last incomplete line

            for (const line of lines) {
                const trimmed = line.trim();
                if (!trimmed) continue;

                const event = JSON.parse(trimmed);

                if (event.type === "progress") {
                    progress.value = event.percent;
                    progressSteps.value = progressSteps.value.map((s) => ({
                        ...s,
                        done: s.done || s.key === event.step,
                    }));
                } else if (event.type === "result") {
                    result.value = event.data;
                }
            }
        }

        rejectedCurrentPage.value = 1;
        await loadRejectedRecords();
    } catch (e) {
        error.value = e.message ?? "Import failed.";
    } finally {
        loading.value = false;
    }
}

async function truncateContacts() {
    if (
        !confirm(
            "Truncate the contacts table? This will delete all imported contacts.",
        )
    ) {
        return;
    }

    debugLoading.value = true;
    error.value = null;
    debugMessage.value = null;

    try {
        const { data } = await axios.post(
            "/api/import/debug/truncate-contacts",
        );
        result.value = null;
        debugMessage.value = data.message ?? "Contacts table truncated.";
        rejectedCurrentPage.value = 1;
        await loadRejectedRecords();
    } catch (e) {
        error.value =
            e.response?.data?.message ?? "Failed to truncate contacts.";
    } finally {
        debugLoading.value = false;
    }
}

async function loadRejectedRecords() {
    rejectedLoading.value = true;

    try {
        const { data } = await axios.get("/api/import/rejected", {
            params: {
                search: rejectedSearch.value || undefined,
                page: rejectedCurrentPage.value,
            },
        });

        rejectedRecords.value = data.data;
        rejectedCurrentPage.value = data.current_page;
        rejectedLastPage.value = data.last_page;
        rejectedTotal.value = data.total;
        rejectedFrom.value = data.from ?? 0;
        rejectedTo.value = data.to ?? 0;
    } finally {
        rejectedLoading.value = false;
    }
}

function goToRejectedPage(page) {
    rejectedCurrentPage.value = page;
    loadRejectedRecords();
}

watch(rejectedSearch, () => {
    clearTimeout(rejectedSearchDebounceTimer);
    rejectedSearchDebounceTimer = setTimeout(() => {
        rejectedCurrentPage.value = 1;
        loadRejectedRecords();
    }, 300);
});

onMounted(loadRejectedRecords);
</script>
