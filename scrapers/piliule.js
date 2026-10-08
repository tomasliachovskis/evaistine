import { runVerskis } from './lib/verskis.js';

// Verskis shop, see lib/verskis.js. Prescription medicines
// (/receptiniai-vaistai) are not scraped.
runVerskis({
    store: 'Piliulė',
    baseUrl: 'https://www.piliule.lt',
    roots: [
        'nereceptiniai-vaistai',
        'vitaminai-ir-maisto-papildai',
        'kosmetika-ir-higiena',
        'medicinines-prekes',
        'arbatos-ir-vaistazoles',
        'kontraceptines-priemones',
    ],
});
