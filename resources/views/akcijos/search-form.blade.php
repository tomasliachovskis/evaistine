<x-layouts.app title="Paieška" :canonical="$canonical" :robots="$robots">
    <div class="mx-auto max-w-xl px-4 py-12 text-center">
        <h1 class="text-2xl font-bold text-gray-900">Ieškoti akcijų</h1>
        <form method="get" action="" class="mt-6" x-data="{ q: '' }" @submit.prevent="if (q.trim()) window.location = '/akcijos/paieska/' + encodeURIComponent(q.trim())">
            <input
                type="search"
                name="q"
                x-model="q"
                placeholder="Ieškoti prekių..."
                class="w-full rounded-full border border-gray-300 px-4 py-2.5 text-sm focus:border-green-600 focus:outline-none"
                autofocus
            >
        </form>
    </div>
</x-layouts.app>
