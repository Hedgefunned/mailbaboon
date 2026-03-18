<template>
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h4 class="text-sm font-semibold text-gray-800 mb-4">Import Summary</h4>
        <div class="space-y-2 text-sm">
            <p class="text-gray-700">
                <span class="font-medium">Total records:</span>
                {{ result.total_records }}
            </p>
            <p class="text-green-700">
                <span class="font-medium">New records:</span>
                {{ result.new_records }}
            </p>
            <p v-if="updatedRecords > 0" class="text-blue-700">
                <span class="font-medium">Updated records:</span>
                {{ updatedRecords }}
            </p>
            <p class="text-amber-700">
                <span class="font-medium">Duplicates (file):</span>
                {{ result.duplicates_in_file }}
            </p>
            <p class="text-orange-700">
                <span class="font-medium">Duplicates (DB):</span>
                {{ result.duplicates_in_db }}
            </p>
            <p class="text-red-700">
                <span class="font-medium">Invalid:</span>
                {{ result.invalid_records }}
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

const updatedRecords = computed(() => {
    const value =
        props.result.owerwritten_records ??
        props.result.overwritten_records ??
        0;

    return Number(value) || 0;
});
</script>
