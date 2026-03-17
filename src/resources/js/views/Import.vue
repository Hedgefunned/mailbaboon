<template>
    <div>
        <h2 class="text-2xl font-semibold text-gray-800 mb-6">Import Contacts</h2>

        <div class="bg-white rounded-xl shadow-sm p-6 max-w-lg">
            <label class="block text-sm font-medium text-gray-700 mb-2">XML file</label>
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
                {{ loading ? 'Importing…' : 'Import' }}
            </button>

            <div v-if="result" class="mt-5 p-4 rounded-lg bg-gray-50 text-sm space-y-1">
                <p class="text-green-700 font-medium">Imported: {{ result.imported }}</p>
                <p class="text-gray-500">Skipped: {{ result.skipped }}</p>
                <p class="text-gray-400">Time: {{ result.execution_time_ms }} ms</p>
                <p class="text-gray-400">Memory: {{ result.memory_peak_mb }} MB (peak)</p>
            </div>

            <div v-if="error" class="mt-5 p-4 rounded-lg bg-red-50 text-sm text-red-700">
                {{ error }}
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from "vue";
import axios from "axios";

const file = ref(null);
const loading = ref(false);
const result = ref(null);
const error = ref(null);

function onFileChange(e) {
    file.value = e.target.files[0] ?? null;
    result.value = null;
    error.value = null;
}

async function upload() {
    if (!file.value) return;

    const form = new FormData();
    form.append("file", file.value);

    loading.value = true;
    result.value = null;
    error.value = null;

    try {
        const { data } = await axios.post("/api/import", form);
        result.value = data;
    } catch (e) {
        error.value = e.response?.data?.message ?? "Import failed.";
    } finally {
        loading.value = false;
    }
}
</script>
