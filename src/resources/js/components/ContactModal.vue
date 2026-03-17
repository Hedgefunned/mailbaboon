<template>
    <div
        class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4"
        @click.self="$emit('close')"
    >
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">
            <div class="flex justify-between items-center px-6 py-4 border-b">
                <h2 class="text-lg font-semibold text-gray-800">
                    {{ contact ? "Edit Contact" : "New Contact" }}
                </h2>
                <button
                    @click="$emit('close')"
                    class="text-gray-400 hover:text-gray-600 text-2xl leading-none"
                >
                    &times;
                </button>
            </div>

            <form @submit.prevent="save" class="px-6 py-5 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                        <input
                            v-model="form.first_name"
                            type="text"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <p v-if="errors.first_name" class="text-red-500 text-xs mt-1">{{ errors.first_name[0] }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                        <input
                            v-model="form.last_name"
                            type="text"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <p v-if="errors.last_name" class="text-red-500 text-xs mt-1">{{ errors.last_name[0] }}</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input
                        v-model="form.email"
                        type="email"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                    <p v-if="errors.email" class="text-red-500 text-xs mt-1">{{ errors.email[0] }}</p>
                </div>

                <div v-if="saveError" class="text-red-500 text-sm">{{ saveError }}</div>

                <div class="flex justify-end gap-3 pt-2">
                    <button
                        type="button"
                        @click="$emit('close')"
                        class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
                    >
                        {{ saving ? "Saving..." : "Save" }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive } from "vue";
import axios from "axios";

const props = defineProps({
    contact: { type: Object, default: null },
});

const emit = defineEmits(["close", "saved"]);

const saving = ref(false);
const saveError = ref(null);
const errors = ref({});

const form = reactive({
    first_name: props.contact?.first_name ?? "",
    last_name: props.contact?.last_name ?? "",
    email: props.contact?.email ?? "",
});

async function save() {
    saving.value = true;
    saveError.value = null;
    errors.value = {};

    try {
        if (props.contact) {
            await axios.patch(`/api/contacts/${props.contact.id}`, form);
        } else {
            await axios.post("/api/contacts", form);
        }
        emit("saved");
    } catch (err) {
        if (err.response?.status === 422) {
            errors.value = err.response.data.errors ?? {};
        } else {
            saveError.value = "Something went wrong. Please try again.";
        }
    } finally {
        saving.value = false;
    }
}
</script>
