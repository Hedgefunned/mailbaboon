<template>
    <div
        class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4"
    >
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">
            <div class="flex justify-between items-center px-6 py-4 border-b">
                <h2 class="text-lg font-semibold text-gray-800">
                    Hello from Modal!
                </h2>
                <button
                    @click="$emit('close')"
                    class="text-gray-400 hover:text-gray-600 text-2xl leading-none"
                >
                    &times;
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive } from "vue";
import axios from "axios";

const emit = defineEmits(["close", "saved"]);

const saving = ref(false);
const saveError = ref(null);
</script>
