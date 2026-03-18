<template>
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-start justify-between gap-4 mb-4">
            <label class="block text-sm font-medium text-gray-700">
                XML file
            </label>
            <button
                :disabled="debugLoading || loading"
                class="cursor-pointer text-xs text-red-600 hover:text-red-700 underline underline-offset-2 disabled:opacity-40 disabled:cursor-not-allowed"
                type="button"
                @click="$emit('truncate-contacts')"
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
            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
            @change="$emit('file-change', $event)"
        />

        <div
            v-if="debugMessage"
            class="mt-4 p-3 rounded-lg bg-amber-50 text-sm text-amber-800"
        >
            {{ debugMessage }}
        </div>

        <div
            v-if="error"
            class="mt-4 p-3 rounded-lg bg-red-50 text-sm text-red-700"
        >
            {{ error }}
        </div>

        <button
            :disabled="!file || loading"
            class="cursor-pointer mt-4 px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
            @click="$emit('upload')"
        >
            {{ loading ? "Importing…" : "Import" }}
        </button>
    </div>
</template>

<script setup>
defineProps({
    debugLoading: {
        type: Boolean,
        default: false,
    },
    debugMessage: {
        type: String,
        default: null,
    },
    error: {
        type: String,
        default: null,
    },
    file: {
        type: Object,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

defineEmits(["file-change", "truncate-contacts", "upload"]);
</script>
