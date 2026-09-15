<x-layouts.shop title="Your data request">
    <h1 class="font-display text-4xl font-semibold">Access or delete your data</h1>
    <p class="mt-2 text-ink-600">Under the CCPA, California residents (and anyone else, as a courtesy) may ask what we hold about them or ask us to delete it. A deletion request removes your name, email, phone and address from our systems — see our <a href="{{ route('legal.privacy') }}" class="font-semibold text-brand-700 underline">privacy policy</a> for why order/lot records themselves are kept, anonymized.</p>

    <form method="post" action="{{ route('privacy-requests.store') }}" class="mt-8 max-w-lg space-y-5 rounded-3xl bg-white p-6 ring-1 ring-bone-200 sm:p-8">
        @csrf

        <fieldset>
            <legend class="text-sm font-bold">What would you like?</legend>
            <div class="mt-2 space-y-1.5">
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="type" value="access" class="accent-brand-700" @checked(old('type', 'access') === 'access') /> Send me a copy of the data you hold about me
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="type" value="delete" class="accent-brand-700" @checked(old('type') === 'delete') /> Delete my personal data
                </label>
            </div>
        </fieldset>

        <label class="block text-sm font-semibold">
            Your name (optional)
            <input name="customer_name" value="{{ old('customer_name') }}" maxlength="120" class="mt-1.5 block h-12 w-full rounded-xl border border-bone-300 bg-bone-50 px-3 text-base focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-100" />
        </label>

        <label class="block text-sm font-semibold">
            Email used on your orders
            <input name="customer_email" type="email" value="{{ old('customer_email') }}" required class="mt-1.5 block h-12 w-full rounded-xl border border-bone-300 bg-bone-50 px-3 text-base focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-100" />
        </label>

        <label class="block text-sm font-semibold">
            Anything else we should know? (optional)
            <textarea name="note" maxlength="500" rows="3" class="mt-1.5 block w-full rounded-xl border border-bone-300 bg-bone-50 px-3 py-2 text-base focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-100">{{ old('note') }}</textarea>
        </label>

        <button class="h-12 w-full rounded-full bg-brand-800 text-sm font-bold text-white hover:bg-brand-700">Submit request</button>
    </form>
</x-layouts.shop>
