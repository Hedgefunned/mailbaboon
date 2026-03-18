<template>
    <div>
        <div class="flex items-end justify-between mb-8 gap-4">
            <div>
                <h2 class="text-2xl font-semibold text-gray-800 mb-2">
                    Dashboard
                </h2>
                <p class="text-gray-500 max-w-2xl">
                    Quick overview of your contacts and shortcuts to the two
                    main workflows.
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl shadow-sm p-6 lg:col-span-2">
                <div class="flex items-end gap-3 mb-4">
                    <p class="text-4xl font-semibold text-gray-900">
                        {{ loading ? "..." : totalContacts }}
                    </p>
                    <p class="text-sm text-gray-500 pb-1">
                        contacts currently stored in MailBaboon
                        {{ totalContacts > 1000 ? "...nice!" : "" }}
                    </p>
                </div>
                <p class="text-sm text-gray-500">
                    This number reflects all contacts available in the main
                    database.
                </p>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-4">
                    Quick Actions
                </h3>
                <div class="space-y-3">
                    <button
                        class="w-full text-center px-4 py-3 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition-colors"
                        @click="$emit('navigate', 'contacts')"
                    >
                        Create Or Manage Contacts
                    </button>
                    <button
                        class="w-full text-center px-4 py-3 rounded-lg bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 transition-colors"
                        @click="$emit('navigate', 'import')"
                    >
                        Go To Import
                    </button>
                </div>
            </div>
        </div>

        <div class="mt-6 bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">
                Basic Information
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div>
                    <p class="text-gray-500 mb-1">Contacts View</p>
                    <p class="text-gray-700">
                        Browse, search, edit, and delete saved contacts.
                    </p>
                </div>
                <div>
                    <p class="text-gray-500 mb-1">Import View</p>
                    <p class="text-gray-700">
                        Upload XML, inspect rejected rows, and review import
                        timings.
                    </p>
                </div>
                <div>
                    <p class="text-gray-500 mb-1">Current Setup</p>
                    <p class="text-gray-700">
                        View selection persists on refresh through hash-based
                        routing.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import axios from "axios";

defineEmits(["navigate"]);

const loading = ref(false);
const totalContacts = ref(0);

async function loadStats() {
    loading.value = true;

    try {
        const { data } = await axios.get("/api/contacts");
        totalContacts.value = data.total ?? 0;
    } finally {
        loading.value = false;
    }
}

onMounted(loadStats);
</script>
