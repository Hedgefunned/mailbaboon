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
}

async function upload() {
    if (!file.value) return;

    const form = new FormData();
    form.append("file", file.value);

    loading.value = true;
    result.value = null;
    error.value = null;
    debugMessage.value = null;

    try {
        const { data } = await axios.post("/api/import", form);
        result.value = data;
        rejectedCurrentPage.value = 1;
        await loadRejectedRecords();
    } catch (e) {
        error.value = e.response?.data?.message ?? "Import failed.";
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
