<x-layouts.app
    app-title="Paieška"
    back-href="/" title="Paieška" :canonical="$canonical" :robots="$robots">
    <div class="mx-auto max-w-xl px-4 py-12 text-center">
        <h1 class="text-2xl font-bold text-gray-900">Ieškoti akcijų</h1>
        <x-search-box class="mx-auto mt-6" :autofocus="true" />
    </div>
</x-layouts.app>
