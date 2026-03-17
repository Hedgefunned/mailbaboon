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

        <ContactModal
            v-if="showModal"
            :contact="editingContact"
            @close="closeModal"
            @saved="onSaved"
        />
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import ContactModal from "../components/ContactModal.vue";

const contacts = ref([]);
const loading = ref(false);
const showModal = ref(false);
const editingContact = ref(null);

async function loadContacts() {
    loading.value = true;
    try {
        const { data } = await axios.get("/api/contacts");
        contacts.value = data;
    } finally {
        loading.value = false;
    }
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
