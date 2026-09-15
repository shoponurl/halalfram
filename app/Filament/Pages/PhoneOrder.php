<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;
use App\Enums\Permission;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\CutOption;
use App\Models\OffalOption;
use App\Models\Order;
use App\Models\PackingOption;
use App\Models\Product;
use App\Support\Weight;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Guideline ch. 6, Sprint 07: phone/counter order entry with customer lookup. Reuses the same
 * App\Actions\Orders\PlaceOrder path the storefront uses — pickup only for v1 (see docs/sprint-07.md
 * for why delivery-by-phone is a disclosed scope cut).
 */
class PhoneOrder extends Page
{
    protected string $view = 'filament.pages.phone-order';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static ?string $navigationLabel = 'Phone/counter order';

    protected static string|UnitEnum|null $navigationGroup = 'Orders';

    protected static ?string $title = 'Phone / counter order';

    /** @var array<int, array{product_id: string, quantity: string, cut_option_id: string, offal_option_id: string, packing_option_id: string}> */
    public array $lines = [];

    public string $lookupPhone = '';

    public string $customerName = '';

    public string $customerEmail = '';

    public string $customerPhone = '';

    public string $notes = '';

    public string $paymentMethod = 'cash';

    /** @var array{lines: list<array{product: Product, quantity: int, weight: Weight, cut_option: ?CutOption, offal_option: ?OffalOption, packing_option: ?PackingOption, lead_time_days: int, estimated_cents: int, hold_cents: int}>, estimated_cents: int, hold_cents: int, lead_time_days: int}|null */
    public ?array $quote = null;

    public ?string $quoteError = null;

    /** @var Collection<int, Product> */
    public Collection $products;

    /** @var Collection<int, CutOption> */
    public Collection $cutOptions;

    /** @var Collection<int, OffalOption> */
    public Collection $offalOptions;

    /** @var Collection<int, PackingOption> */
    public Collection $packingOptions;

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ManageOrders->value) ?? false;
    }

    public function mount(): void
    {
        $this->lines = [$this->emptyLine()];
        $this->products = Product::query()->active()->orderBy('name')->get();
        $this->cutOptions = CutOption::query()->active()->orderBy('name')->get();
        $this->offalOptions = OffalOption::query()->active()->orderBy('name')->get();
        $this->packingOptions = PackingOption::query()->active()->orderBy('name')->get();
    }

    /** @return array{product_id: string, quantity: string, cut_option_id: string, offal_option_id: string, packing_option_id: string} */
    private function emptyLine(): array
    {
        return ['product_id' => '', 'quantity' => '1', 'cut_option_id' => '', 'offal_option_id' => '', 'packing_option_id' => ''];
    }

    public function addLine(): void
    {
        $this->lines[] = $this->emptyLine();
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        $this->requote();
    }

    /** Finds the most recent order for a phone number and prefills the customer fields from it. */
    public function lookupCustomer(): void
    {
        $order = Order::query()->where('customer_phone', trim($this->lookupPhone))->latest('id')->first();
        if ($order === null) {
            Notification::make()->warning()->title('No past order found for that phone number')->send();

            return;
        }

        $this->customerName = $order->customer_name;
        $this->customerEmail = $order->customer_email;
        $this->customerPhone = $order->customer_phone;
        Notification::make()->success()->title("Found {$order->customer_name} (last order {$order->number})")->send();
    }

    public function requote(): void
    {
        $rawLines = $this->rawLines();
        if ($rawLines === []) {
            $this->quote = null;
            $this->quoteError = null;

            return;
        }

        try {
            $this->quote = app(QuoteCart::class)->handle($rawLines);
            $this->quoteError = null;
        } catch (ValidationException $e) {
            $this->quote = null;
            $this->quoteError = collect($e->errors())->flatten()->first();
        }
    }

    /** @return array<int, array{product_id: int, quantity: int, cut_option_id: int|null, offal_option_id: int|null, packing_option_id: int|null}> */
    private function rawLines(): array
    {
        $lines = [];
        foreach ($this->lines as $line) {
            if ($line['product_id'] === '' || (int) $line['quantity'] < 1) {
                continue;
            }
            $lines[] = [
                'product_id' => (int) $line['product_id'],
                'quantity' => (int) $line['quantity'],
                'cut_option_id' => $line['cut_option_id'] !== '' ? (int) $line['cut_option_id'] : null,
                'offal_option_id' => $line['offal_option_id'] !== '' ? (int) $line['offal_option_id'] : null,
                'packing_option_id' => $line['packing_option_id'] !== '' ? (int) $line['packing_option_id'] : null,
            ];
        }

        return $lines;
    }

    public function submit(): void
    {
        $this->requote();
        if ($this->quote === null) {
            Notification::make()->danger()->title($this->quoteError ?? 'Add at least one valid line first.')->send();

            return;
        }
        if ($this->customerName === '' || $this->customerEmail === '' || $this->customerPhone === '') {
            Notification::make()->danger()->title('Customer name, email and phone are all required.')->send();

            return;
        }

        try {
            $result = app(PlaceOrder::class)->handle(
                lines: $this->rawLines(),
                customer: [
                    'customer_name' => $this->customerName,
                    'customer_email' => strtolower($this->customerEmail),
                    'customer_phone' => $this->customerPhone,
                    'notes' => $this->notes !== '' ? $this->notes : null,
                ],
                expectedHoldCents: $this->quote['hold_cents'],
                fulfilment: ['method' => 'pickup'],
                user: null,
                paymentMethod: $this->paymentMethod,
                regulatoryConsent: true,
            );
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();

            return;
        }

        $order = $result['order'];
        Notification::make()->success()->title("Order {$order->number} placed")->send();

        $this->lines = [$this->emptyLine()];
        $this->customerName = '';
        $this->customerEmail = '';
        $this->customerPhone = '';
        $this->notes = '';
        $this->quote = null;
        $this->redirect(OrderResource::getUrl('view', ['record' => $order]));
    }
}
