<x-layouts.storefront>
    <main id="main">
        {{-- Home view (hidden while a product page or other page is shown) --}}
        <div id="homeView">
            <x-home.hero />
            <x-home.guarantees />
            <x-home.categories />
            <x-home.shop />
            <x-home.services />
            <x-home.standard />
            <x-home.faq />
            <x-home.visit />
        </div>

        {{-- Product details page (rendered by the storefront script for #/product/<id>) --}}
        <section id="pdp" class="mx-auto max-w-7xl px-4 pb-24 pt-6 sm:px-6 lg:px-8" aria-labelledby="pdpTitle" hidden></section>

        {{-- Other pages (checkout, order, wishlist, account, info pages) --}}
        <section id="pageView" class="mx-auto max-w-7xl px-4 pb-24 pt-6 sm:px-6 lg:px-8" aria-labelledby="pageTitle" hidden></section>
    </main>
</x-layouts.storefront>
