import { runVerskis } from './lib/verskis.js';

// Verskis shop, see lib/verskis.js. "Kūno priežiūra" in the top menu is a
// link into "Kosmetika ir higiena", so it isn't listed separately.
runVerskis({
    store: 'Universiteto vaistinė',
    baseUrl: 'https://www.universitetovaistine.eu',
    roots: [
        'maisto-papildai-ir-vitaminai',
        'kosmetika-ir-higiena',
        'medicinos-priemones',
        'vaistineje-gaminama-kosmetika',
    ],
});
