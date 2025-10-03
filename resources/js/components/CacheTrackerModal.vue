<script setup>
import axios from 'axios';
import { ref } from 'vue';

const props = defineProps({
    values: { type: Object, required: true },
});

const getColor = (tag) => {
    if (tag.indexOf('entry::') === 0) {
        return 'green';
    }

    if (tag.indexOf('collection::') === 0) {
        return 'yellow';
    }

    if (tag.indexOf('term::') === 0) {
        return 'rose';
    }

    if (tag.indexOf('global::') === 0) {
        return 'violet';
    }

    if (tag.indexOf('partial::') === 0) {
        return 'blue';
    }

    return null;
}

const setShowWhat = (what) => {
    show.value = what;
}

const item = props.values?.length ? (props.values[0] ?? '') : '';
const show = ref('tags');
const tags = ref([]);
const urls = ref([]);

axios
    .post(cp_url(`/cache-tracker/tags`), { item: item })
    .then(response => {
        tags.value = response.data;
    })
    .catch((e) => { });

axios
    .post(cp_url(`/cache-tracker/urls`), { item: item })
    .then(response => {
        urls.value = response.data;
    })
    .catch((e) => { });
</script>

<template>
    <div>
        <ui-button-group class="mb-4">
            <ui-button :variant="show == 'tags' ? 'primary' : 'default'" size="sm" @click="setShowWhat('tags')">Tags on this URL</ui-button>
            <ui-button :variant="show == 'urls' ? 'primary' : 'default'"  size="sm" @click="setShowWhat('urls')">URLs with this item</ui-button>
        </ui-button-group>

        <div v-if="show == 'tags'">

            <ui-description v-text="__('There are no tags tracked for :item.', { item: item })" v-if="tags.length < 1"></ui-description>

            <ui-description v-text="__('The following tags are being tracked for :item:', { item: item })" v-if="tags.length"></ui-description>

            <div class="flex gap-2 mt-4" v-if="tags.length">

                <template v-for="tag in tags">
                    <ui-badge pill :color="getColor(tag)" v-text="tag"></ui-badge>
                </template>

            </div>

        </div>

        <div v-if="show == 'urls'">
            <ui-description v-text="__('There are no urls tracked containing :item.', { item: item })" v-if="urls.length < 1"></ui-description>

            <ui-description v-text="__('The following URLs contain :item:', { item: 'ryan' })" v-if="urls.length"></ui-description>

            <div class="flex gap-2 mt-4" v-if="urls.length">

                <template v-for="url in urls">
                    <a :href="url" v-text="url"></a><br />
                </template>

            </div>

        </div>
    </div>
</template>
