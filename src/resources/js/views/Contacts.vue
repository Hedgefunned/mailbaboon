<template>
    <div>
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-semibold text-gray-800">Contacts</h2>
            <button
                @click="openModal(null)"
                class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors"
            >
                + New Contact
            </button>
        </div>

        <div class="mb-4">
            <input
                v-model="search"
                type="search"
                placeholder="Search by name or email…"
                class="w-full px-4 py-2 text-sm border border-gray-200 rounded-lg bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
            />
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div v-if="loading" class="p-8 text-center text-gray-400">Loading...</div>

            <div v-else-if="contacts.length === 0" class="p-8 text-center text-gray-400">
                No contacts found.
            </div>

            <table v-else class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="text-left px-5 py-3 font-medium text-gray-500">Name</th>
                        <th class="text-left px-5 py-3 font-medium text-gray-500">Email</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="contact in contacts" :key="contact.id" class="hover:bg-gray-50">
                        <td class="px-5 py-3 font-medium text-gray-800">
                            {{ contact.first_name }} {{ contact.last_name }}
                        </td>
                        <td class="px-5 py-3 text-gray-600">{{ contact.email }}</td>
                        <td class="px-5 py-3 text-right space-x-2">
                            <button
                                @click="openModal(contact)"
                                class="text-blue-600 hover:text-blue-800 text-xs font-medium"
                            >
                                Edit
                            </button>
                            <button
                                @click="confirmDelete(contact)"
                                class="text-red-500 hover:text-red-700 text-xs font-medium"
                            >
                                Delete
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="lastPage > 1" class="flex items-center justify-between mt-4 text-sm text-gray-500">
            <span>{{ from }}–{{ to }} of {{ total }}</span>
            <div class="flex items-center gap-1">
                <button
                    @click="goToPage(currentPage - 1)"
                    :disabled="currentPage === 1"
                    class="px-3 py-1.5 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"
                >
                    ‹ Prev
                </button>
                <span class="px-3 py-1.5">{{ currentPage }} / {{ lastPage }}</span>
                <button
                    @click="goToPage(currentPage + 1)"
                    :disabled="currentPage === lastPage"
                    class="px-3 py-1.5 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"
                >
                    Next ›
                </button>
            </div>
        </div>

        <ContactModal
            v-if="showModal"
            :contact="editingContact"
            @close="closeModal"
            @saved="onSaved"
        />
    </div>
</template>

<script setup>
import { ref, watch, onMounted } from "vue";
import axios from "axios";
import ContactModal from "../components/ContactModal.vue";

const contacts = ref([]);
const loading = ref(false);
const showModal = ref(false);
const editingContact = ref(null);

const search = ref("");
const currentPage = ref(1);
const lastPage = ref(1);
const total = ref(0);
const from = ref(0);
const to = ref(0);

let debounceTimer = null;

async function loadContacts() {
    loading.value = true;
    try {
        const { data } = await axios.get("/api/contacts", {
            params: { search: search.value || undefined, page: currentPage.value },
        });
        contacts.value = data.data;
        currentPage.value = data.current_page;
        lastPage.value = data.last_page;
        total.value = data.total;
        from.value = data.from ?? 0;
        to.value = data.to ?? 0;
    } finally {
        loading.value = false;
    }
}

watch(search, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        currentPage.value = 1;
        loadContacts();
    }, 300);
});

function goToPage(page) {
    currentPage.value = page;
    loadContacts();
}

function openModal(contact) {
    editingContact.value = contact;
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    editingContact.value = null;
}

async function confirmDelete(contact) {
    if (!confirm(`Delete ${contact.first_name} ${contact.last_name}?`)) return;
    await axios.delete(`/api/contacts/${contact.id}`);
    await loadContacts();
}

function onSaved() {
    closeModal();
    loadContacts();
}

onMounted(loadContacts);
</script>
