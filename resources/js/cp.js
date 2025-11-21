import CacheTrackerModal from './components/CacheTrackerModal.vue';
import ClearUtility from "./pages/ClearUtility.vue";
import { inertia } from '@statamic/cms/api';

Statamic.booting(() => {
    Statamic.$components.register('cache-tracker-modal', CacheTrackerModal);

   inertia.register('CacheTracker/ClearUtility', ClearUtility);
});
