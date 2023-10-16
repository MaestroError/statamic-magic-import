import Importer from './components/Importer.vue'

Statamic.booting(() => {
    Statamic.$components.register('json-importer', Importer)
})
