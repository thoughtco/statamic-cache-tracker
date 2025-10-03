import CacheTrackerModal from './components/CacheTrackerModal.vue';

Statamic.booting(() => {
    Statamic.$components.register('cache-tracker-modal', CacheTrackerModal);
});
