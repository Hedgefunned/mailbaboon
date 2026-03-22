<template>
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h4 class="text-sm font-semibold text-gray-800 mb-4">Performance</h4>

        <!-- Streaming metrics -->
        <div v-if="isStreaming" class="space-y-2 text-sm text-gray-600">
            <p>
                <span class="font-medium">Parse:</span>
                {{ result.parse_time_ms }} ms
            </p>
            <p>
                <span class="font-medium">Load:</span>
                {{ result.load_time_ms }} ms
            </p>
            <p>
                <span class="font-medium">File Dedupe:</span>
                {{ result.dedupe_time_ms }} ms
            </p>
            <p>
                <span class="font-medium">DB Dedupe:</span>
                {{ result.existing_contacts_dedupe_time_ms }} ms
            </p>
            <p>
                <span class="font-medium">DB Insert:</span>
                {{ result.insert_valid_contacts_time_ms }} ms
            </p>
            <hr class="my-2 border-gray-200" />
            <p class="font-semibold text-gray-800">
                <span>Total:</span> {{ result.execution_time_ms }} ms
            </p>
            <p class="text-xs text-gray-500">
                <span class="font-medium">Memory:</span>
                {{ result.memory_peak_mb }} MB
            </p>
        </div>

        <!-- Chunked metrics -->
        <div v-else class="space-y-2 text-sm text-gray-600">
            <p>
                <span class="font-medium">Parse:</span>
                {{ result.parse_time_ms }} ms
            </p>
            <p>
                <span class="font-medium">Chunks dispatched:</span>
                {{ result.chunks_dispatched }}
            </p>
            <p class="text-xs text-gray-500">
                Processing was handled asynchronously by queue workers.
            </p>
            <hr class="my-2 border-gray-200" />
            <p class="font-semibold text-gray-800">
                <span>Total:</span> {{ result.execution_time_ms }} ms
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
    result: {
        type: Object,
        required: true,
    },
});

const isStreaming = computed(() => props.result.load_time_ms !== undefined);
</script>
