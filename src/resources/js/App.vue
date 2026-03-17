<template>
    <div class="flex h-screen bg-gray-100">
        <aside class="w-56 bg-white shadow-sm flex flex-col shrink-0">
            <div class="p-5 border-b">
                <h1 class="text-lg font-bold text-gray-900">Playground</h1>
            </div>
            <nav class="flex-1 p-3 space-y-1">
                <button
                    @click="currentView = 'active'"
                    :class="[
                        'w-full text-left px-3 py-2 rounded-lg text-sm transition-colors',
                        currentView === 'active'
                            ? 'bg-blue-50 text-blue-700 font-medium'
                            : 'text-gray-600 hover:bg-gray-100',
                    ]"
                >
                    Active
                </button>
                <button
                    @click="currentView = 'archived'"
                    :class="[
                        'w-full text-left px-3 py-2 rounded-lg text-sm transition-colors',
                        currentView === 'archived'
                            ? 'bg-blue-50 text-blue-700 font-medium'
                            : 'text-gray-600 hover:bg-gray-100',
                    ]"
                >
                    Archive
                </button>
            </nav>
        </aside>

        <main class="flex-1 overflow-auto p-8">
            <div class="flex justify-between items-center mb-12">
                <h2 class="text-2xl font-semibold text-gray-800">
                    {{ currentView === "active" ? "Active" : "Archive" }}
                </h2>
                <button
                    v-if="currentView === 'active'"
                    @click="openModal(null)"
                    class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors"
                >
                    + New
                </button>
            </div>
        </main>

        <Modal v-if="showModal" @close="closeModal" />
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from "vue";
import Modal from "./components/Modal.vue";
import axios from "axios";

const currentView = ref("active");
const loading = ref(true);
const error = ref(null);
const showModal = ref(false);

function openModal(note) {
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
}
</script>
