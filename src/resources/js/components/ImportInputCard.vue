<template>
    <div class="bg-white rounded-xl shadow-sm p-6 flex flex-col">
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

        <!-- Mode toggle -->
        <div v-if="!loading" class="flex gap-4 mb-4">
            <label
                v-for="opt in modeOptions"
                :key="opt.value"
                class="flex items-start gap-2 text-sm cursor-pointer"
            >
                <input
                    type="radio"
                    :value="opt.value"
                    :checked="mode === opt.value"
                    class="mt-0.5 text-blue-600 focus:ring-blue-500"
                    @change="$emit('update:mode', opt.value)"
                />
                <span>
                    <span class="font-medium text-gray-700">{{
                        opt.label
                    }}</span>
                    <span class="block text-xs text-gray-500 mt-0.5">{{
                        opt.description
                    }}</span>
                </span>
            </label>
        </div>

        <!-- Progress UI (shown while importing and after completion) -->
        <div v-if="showProgress && progress !== null" class="mb-4">
            <div>
                <div class="flex justify-between text-sm text-gray-600 mb-1">
                    <span>{{
                        loading ? "Importing…" : "Import complete"
                    }}</span>
                    <span>{{ progress }}%</span>
                </div>
                <div
                    class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden"
                >
                    <div
                        class="bg-blue-600 h-2.5 rounded-full transition-all duration-500 ease-out"
                        :style="{ width: progress + '%' }"
                    />
                </div>
            </div>

            <ul class="space-y-1.5 mt-4">
                <li
                    v-for="step in progressSteps"
                    :key="step.key"
                    class="flex items-center gap-2 text-sm"
                    :class="step.done ? 'text-gray-700' : 'text-gray-400'"
                >
                    <span
                        class="inline-flex items-center justify-center w-4 h-4 rounded-full text-xs flex-shrink-0"
                        :class="
                            step.done
                                ? 'bg-blue-600 text-white'
                                : 'bg-gray-200 text-gray-400'
                        "
                    >
                        <svg
                            v-if="step.done"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 12 12"
                            fill="none"
                            class="w-2.5 h-2.5"
                        >
                            <path
                                d="M2 6l3 3 5-5"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                    </span>
                    {{ step.label }}
                </li>
            </ul>
        </div>

        <!-- File input (shown when not loading) -->
        <div v-if="!loading">
            <input
                type="file"
                accept=".xml"
                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                @change="$emit('file-change', $event)"
            />

            <label class="mt-4 flex items-start gap-2 text-sm text-gray-700">
                <input
                    type="checkbox"
                    class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    :checked="overwriteExisting"
                    @change="
                        $emit(
                            'update:overwrite-existing',
                            $event.target.checked,
                        )
                    "
                />
                <span>
                    <span class="font-medium">Overwrite existing contacts</span>
                    <span class="block text-xs text-gray-500 mt-0.5">
                        When enabled, contacts with matching email will be
                        updated instead of skipped.
                    </span>
                </span>
            </label>

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
                :disabled="!file"
                class="cursor-pointer mt-4 px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                @click="$emit('upload')"
            >
                Import
            </button>
        </div>
    </div>
</template>

<script setup>
const modeOptions = [
    {
        value: "streaming",
        label: "Streaming",
        description: "Fast, synchronous. Requires DB server config.",
    },
    {
        value: "chunked",
        label: "Chunked (async)",
        description: "Batched background jobs. No DB reconfiguration needed.",
    },
];

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
    mode: {
        type: String,
        default: "streaming",
    },
    progress: {
        type: Number,
        default: null,
    },
    progressSteps: {
        type: Array,
        default: () => [],
    },
    showProgress: {
        type: Boolean,
        default: false,
    },
    overwriteExisting: {
        type: Boolean,
        default: false,
    },
});

defineEmits([
    "file-change",
    "truncate-contacts",
    "upload",
    "update:mode",
    "update:overwrite-existing",
]);
</script>
