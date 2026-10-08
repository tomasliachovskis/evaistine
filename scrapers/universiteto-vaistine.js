import { runVerskis } from './lib/verskis.js';

// Verskis shop, see lib/verskis.js. "Kūno priežiūra" in the top menu is a
// link into "Kosmetika ir higiena", so it isn't listed separately.
runVerskis({
    store: 'Universiteto vaistinė',
    baseUrl: 'https://www.universitetovaistine.eu',
    // No 384x384.g copies on this shop (404): use the original image.
    imageSize: null,
    roots: [
        'maisto-papildai-ir-vitaminai',
        'kosmetika-ir-higiena',
        'medicinos-priemones',
        'vaistineje-gaminama-kosmetika',
    ],
});
