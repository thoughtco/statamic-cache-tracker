<script setup>
import axios from 'axios';
import { ref } from 'vue';

const urls = ref('');

const clearCache = async () => {
    let response = await axios.post(cp_url('utilities/static-cache-tracker/clear'), { urls: urls.value });

    Statamic.$toast.success(response.data.message);
}

</script>

<template>
    <ui-header :title="__('Cache Tracker')" icon="taxonomies">
        <ui-button class="btn-primary" v-text="__('Clear everything')" @click="urls = '*'; clearCache();" />
    </ui-header>

    <ui-panel class="flex flex-col">
        <ui-panel-header class="flex items-center justify-between min-h-10">
            <ui-heading>{{ __('Enter URLs to clear, with each on a new line. You can use * as a wildcard at the end of your URL.') }}</ui-heading>
        </ui-panel-header>

        <ui-card class="flex-1">

            <ui-field>
                <ui-textarea label="Enter URLs" v-model="urls" />
            </ui-field>

            <ui-field class="mt-4 flex justify-end">
                <ui-button variant="primary" v-text="__('Clear')" @click="clearCache" />
            </ui-field>

        </ui-card>
    </ui-panel>

</template>
