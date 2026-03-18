<template>
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h4 class="text-sm font-semibold text-gray-800 mb-4">Performance</h4>
        <div class="space-y-2 text-sm text-gray-600">
            <p>
                <span class="font-medium">Parse:</span>
                {{ result.parse_time_ms }} ms
            </p>
            <p>
                <span class="font-medium">Load:</span>
                {{ result.load_time_ms }} ms
            </p>
            <p>
                <span class="font-medium">Input Dedupe:</span>
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
    </div>
</template>

<script setup>
defineProps({
    result: {
        type: Object,
        required: true,
    },
});
</script>
