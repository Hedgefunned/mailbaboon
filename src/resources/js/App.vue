<template>
    <div class="flex h-screen bg-gray-100">
        <aside class="w-56 bg-white shadow-sm flex flex-col shrink-0">
            <div class="p-5 border-b">
                <h1 class="text-lg font-bold text-gray-900">MailBaboon</h1>
            </div>
            <nav class="flex-1 p-3 space-y-1">
                <button
                    v-for="item in navItems"
                    :key="item.view"
                    @click="setCurrentView(item.view)"
                    :class="[
                        'w-full text-left px-3 py-2 rounded-lg text-sm transition-colors',
                        currentView === item.view
                            ? 'bg-blue-50 text-blue-700 font-medium'
                            : 'text-gray-600 hover:bg-gray-100',
                    ]"
                >
                    {{ item.label }}
                </button>
            </nav>
        </aside>

        <main class="flex-1 overflow-auto p-8">
            <Dashboard
                v-if="currentView === 'dashboard'"
                @navigate="setCurrentView"
            />
            <Contacts v-else-if="currentView === 'contacts'" />
            <Import v-else-if="currentView === 'import'" />
        </main>
    </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from "vue";
import Dashboard from "./views/Dashboard.vue";
import Contacts from "./views/Contacts.vue";
import Import from "./views/Import.vue";

const navItems = [
    { label: "Dashboard", view: "dashboard" },
    { label: "Contacts", view: "contacts" },
    { label: "Import", view: "import" },
];

const defaultView = "contacts";
const validViews = new Set(navItems.map((item) => item.view));
const currentView = ref(resolveViewFromHash());

function resolveViewFromHash() {
    const view = window.location.hash.replace(/^#/, "");

    return validViews.has(view) ? view : defaultView;
}

function syncViewFromHash() {
    currentView.value = resolveViewFromHash();
}

function setCurrentView(view) {
    if (!validViews.has(view)) {
        return;
    }

    currentView.value = view;
    window.location.hash = view;
}

onMounted(() => {
    if (!window.location.hash) {
        window.location.hash = currentView.value;
    }

    window.addEventListener("hashchange", syncViewFromHash);
});

onUnmounted(() => {
    window.removeEventListener("hashchange", syncViewFromHash);
});
</script>
