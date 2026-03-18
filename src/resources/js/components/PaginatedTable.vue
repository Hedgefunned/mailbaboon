<template>
    <div>
        <div
            v-if="showToolbar"
            class="flex items-center justify-between mb-4 gap-3"
        >
            <div v-if="$slots.title">
                <slot name="title" />
            </div>
            <input
                v-if="searchPlaceholder"
                :value="search"
                type="search"
                :placeholder="searchPlaceholder"
                class="w-full max-w-sm px-4 py-2 text-sm border border-gray-200 rounded-lg bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
                @input="$emit('update:search', $event.target.value)"
            />
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div v-if="loading" class="p-8 text-center text-gray-400">
                {{ loadingMessage }}
            </div>

            <div
                v-else-if="items.length === 0"
                class="p-8 text-center text-gray-400"
            >
                {{ emptyMessage }}
            </div>

            <table v-else class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <slot name="header" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr
                        v-for="item in items"
                        :key="item.id"
                        class="hover:bg-gray-50"
                    >
                        <slot name="row" :item="item" />
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="lastPage > 1"
            class="flex items-center justify-between mt-4 text-sm text-gray-500"
        >
            <span>{{ from }}-{{ to }} of {{ total }}</span>
            <div class="flex items-center gap-1">
                <button
                    :disabled="currentPage === 1"
                    class="px-3 py-1.5 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"
                    @click="$emit('page-change', currentPage - 1)"
                >
                    Prev
                </button>
                <span class="px-3 py-1.5"
                    >{{ currentPage }} / {{ lastPage }}</span
                >
                <button
                    :disabled="currentPage === lastPage"
                    class="px-3 py-1.5 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"
                    @click="$emit('page-change', currentPage + 1)"
                >
                    Next
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, useSlots } from "vue";

const props = defineProps({
    currentPage: {
        type: Number,
        required: true,
    },
    emptyMessage: {
        type: String,
        default: "No results found.",
    },
    from: {
        type: Number,
        default: 0,
    },
    items: {
        type: Array,
        default: () => [],
    },
    lastPage: {
        type: Number,
        required: true,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    loadingMessage: {
        type: String,
        default: "Loading...",
    },
    search: {
        type: String,
        default: "",
    },
    searchPlaceholder: {
        type: String,
        default: "",
    },
    to: {
        type: Number,
        default: 0,
    },
    total: {
        type: Number,
        default: 0,
    },
});

defineEmits(["page-change", "update:search"]);

const slots = useSlots();

const showToolbar = computed(
    () => Boolean(props.searchPlaceholder) || Boolean(slots.title),
);
</script>
