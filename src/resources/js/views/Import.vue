<template>
    <div>
        <h2 class="text-2xl font-semibold text-gray-800 mb-6">
            Import Contacts
        </h2>

        <div class="bg-white rounded-xl shadow-sm p-6 max-w-lg">
            <div class="flex items-start justify-between gap-4">
                <label class="block text-sm font-medium text-gray-700 mb-2"
                    >XML file</label
                >
                <button
                    @click="truncateContacts"
                    :disabled="debugLoading || loading"
                    class="text-xs text-red-600 hover:text-red-700 underline underline-offset-2 disabled:opacity-40 disabled:cursor-not-allowed"
                    type="button"
                >
                    {{
                        debugLoading
                            ? "Truncating..."
                            : "Debug: truncate contacts table"
                    }}
                </button>
            </div>
            <input
                type="file"
                accept=".xml"
                @change="onFileChange"
                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
            />

            <button
                @click="upload"
                :disabled="!file || loading"
                class="mt-4 px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
            >
                {{ loading ? "Importing…" : "Import" }}
            </button>

            <div
                v-if="result"
                class="mt-5 p-4 rounded-lg bg-gray-50 text-sm space-y-1"
            >
                <p class="text-gray-700 font-medium">
                    Total records in file: {{ result.total_records }}
                </p>
                <p class="text-green-700 font-medium">
                    New records: {{ result.new_records }}
                </p>
                <p class="text-amber-700">
                    Duplicates in file: {{ result.duplicates_in_file }}
                </p>
                <p class="text-orange-700">
                    Duplicates in DB: {{ result.duplicates_in_db }}
                </p>
                <p class="text-red-700">
                    Invalid records: {{ result.invalid_records }}
                </p>
                <hr class="my-2 border-gray-200" />
                <p class="text-gray-400">
                    Parse: {{ result.parse_time_ms }} ms
                </p>
                <p class="text-gray-400">Load: {{ result.load_time_ms }} ms</p>
                <p class="text-gray-400">
                    Input Dedupe: {{ result.dedupe_time_ms }} ms
                </p>
                <p class="text-gray-400">
                    DB Dedupe: {{ result.existing_contacts_dedupe_time_ms }} ms
                </p>
                <p class="text-gray-400">
                    DB Insert: {{ result.insert_valid_contacts_time_ms }} ms
                </p>
                <p class="text-gray-400">
                    Time: {{ result.execution_time_ms }} ms
                </p>
                <p class="text-gray-400">
                    Memory: {{ result.memory_peak_mb }} MB (peak)
                </p>
            </div>

            <div
                v-if="debugMessage"
                class="mt-5 p-4 rounded-lg bg-amber-50 text-sm text-amber-800"
            >
                {{ debugMessage }}
            </div>

            <div
                v-if="error"
                class="mt-5 p-4 rounded-lg bg-red-50 text-sm text-red-700"
            >
                {{ error }}
            </div>
        </div>

        <div class="mt-8">
            <div class="flex items-center justify-between mb-4 gap-3">
                <h3 class="text-xl font-semibold text-gray-800">
                    Rejected Records
                </h3>
                <input
                    v-model="rejectedSearch"
                    type="search"
                    placeholder="Search rejected records..."
                    class="w-full max-w-sm px-4 py-2 text-sm border border-gray-200 rounded-lg bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
                />
            </div>

            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div
                    v-if="rejectedLoading"
                    class="p-8 text-center text-gray-400"
                >
                    Loading rejected records...
                </div>

                <div
                    v-else-if="rejectedRecords.length === 0"
                    class="p-8 text-center text-gray-400"
                >
                    No rejected records found.
                </div>

                <table v-else class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th
                                class="text-left px-5 py-3 font-medium text-gray-500"
                            >
                                Name
                            </th>
                            <th
                                class="text-left px-5 py-3 font-medium text-gray-500"
                            >
                                Email
                            </th>
                            <th
                                class="text-left px-5 py-3 font-medium text-gray-500"
                            >
                                Reason
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr
                            v-for="record in rejectedRecords"
                            :key="record.id"
                            class="hover:bg-gray-50"
                        >
                            <td class="px-5 py-3 font-medium text-gray-800">
                                {{ record.first_name }} {{ record.last_name }}
                            </td>
                            <td class="px-5 py-3 text-gray-600">
                                {{ record.email }}
                            </td>
                            <td class="px-5 py-3 text-red-600">
                                {{ record.failure_reason }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="rejectedLastPage > 1"
                class="flex items-center justify-between mt-4 text-sm text-gray-500"
            >
                <span
                    >{{ rejectedFrom }}-{{ rejectedTo }} of
                    {{ rejectedTotal }}</span
                >
                <div class="flex items-center gap-1">
                    <button
                        @click="goToRejectedPage(rejectedCurrentPage - 1)"
                        :disabled="rejectedCurrentPage === 1"
                        class="px-3 py-1.5 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                        Prev
                    </button>
                    <span class="px-3 py-1.5">
                        {{ rejectedCurrentPage }} / {{ rejectedLastPage }}
                    </span>
                    <button
                        @click="goToRejectedPage(rejectedCurrentPage + 1)"
                        :disabled="rejectedCurrentPage === rejectedLastPage"
                        class="px-3 py-1.5 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";
import axios from "axios";

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
