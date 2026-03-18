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

        <PaginatedTable
            :current-page="currentPage"
            :empty-message="'No contacts found.'"
            :from="from"
            :items="contacts"
            :last-page="lastPage"
            :loading="loading"
            :search="search"
            :search-placeholder="'Search by name or email…'"
            :to="to"
            :total="total"
            @page-change="goToPage"
            @update:search="search = $event"
        >
            <template #header>
                <th class="text-left px-5 py-3 font-medium text-gray-500">
                    Name
                </th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">
                    Email
                </th>
                <th class="px-5 py-3"></th>
            </template>

            <template #row="{ item: contact }">
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
            </template>
        </PaginatedTable>

        <ContactModal
            v-if="showModal"
            :contact="editingContact"
            @close="closeModal"
            @saved="onSaved"
        />
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";
import axios from "axios";
import ContactModal from "../components/ContactModal.vue";
import PaginatedTable from "../components/PaginatedTable.vue";

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
            params: {
                search: search.value || undefined,
                page: currentPage.value,
            },
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
