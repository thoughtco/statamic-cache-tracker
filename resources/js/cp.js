import CacheTrackerModal from './components/CacheTrackerModal.vue';
import ClearUtility from "./pages/ClearUtility.vue";

Statamic.booting(() => {
    Statamic.$components.register('cache-tracker-modal', CacheTrackerModal);

    Statamic.$components.register('Pages/CacheTracker/ClearUtility', ClearUtility);
});
