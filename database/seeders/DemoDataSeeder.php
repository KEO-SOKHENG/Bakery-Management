<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProductImageStorage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Adds demo data and generated product images for LOCAL testing only.
 *
 * Safe to run more than once (rows are matched by SKU / name / email and skipped when present).
 * Refuses to run outside local/testing, against a non-local database host, or when the media
 * disk is not a local disk, so it can never touch Production, Supabase or any remote storage.
 *
 *   php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    private const LOCAL_HOSTS = ['127.0.0.1', 'localhost', '::1', 'host.docker.internal'];

    public function run(): void
    {
        $this->guard();

        $cats = $this->categories();
        $products = $this->products($cats);
        $this->suppliersAndIngredients($products);
        $customers = $this->customers();
        $orders = $this->orders($products, $customers);

        $this->command?->info('Demo data ready: ' . count($products) . ' products (with images), ' . count($customers) . ' customers, ' . $orders . ' new orders.');
    }

    // ── Safety ──
    private function guard(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('DemoDataSeeder only runs in the local or testing environment.');
        }

        $conn = config('database.default');
        $host = (string) config("database.connections.{$conn}.host");
        if (config("database.connections.{$conn}.driver") === 'pgsql' && !in_array($host, self::LOCAL_HOSTS, true)) {
            throw new \RuntimeException('Refusing to seed: the database host is not local.');
        }

        $disk = app(ProductImageStorage::class)->disk();
        $driver = config('filesystems.disks.' . app(ProductImageStorage::class)->diskName() . '.driver');
        if ($driver !== 'local') {
            throw new \RuntimeException('Refusing to seed: the media disk is not a local disk.');
        }
    }

    // ── Categories ──
    private function categories(): array
    {
        $defs = [
            'Cakes' => 'Freshly baked cakes and pastries',
            'Bread' => 'Artisanal breads and loaves',
            'Pastries' => 'Flaky French pastries',
            'Cookies' => 'Sweet baked cookies',
            'Drinks' => 'Coffee, tea and beverage options',
            'Sandwiches' => 'Fresh sandwiches and savory bites',
            'Seasonal' => 'Limited-time seasonal specials',
        ];
        $out = [];
        foreach ($defs as $name => $desc) {
            $out[$name] = Category::firstOrCreate(['name' => $name], ['description' => $desc, 'status' => 'active']);
        }

        return $out;
    }

    // ── Products + generated images ──
    private function products(array $cats): array
    {
        // [name, category, price, cost, stock, emoji, [color1, color2]]
        $defs = [
            ['Strawberry Shortcake', 'Cakes', 4.50, 2.10, 24, '🍰', ['#ff9aa2', '#ff6f91']],
            ['Red Velvet Slice', 'Cakes', 4.20, 1.90, 18, '🍰', ['#e63946', '#9d0208']],
            ['Tiramisu Cup', 'Cakes', 3.80, 1.60, 20, '🍮', ['#c9a27a', '#7f5539']],
            ['Lemon Tart', 'Cakes', 3.60, 1.40, 16, '🥧', ['#ffe66d', '#f9c74f']],
            ['Sourdough Loaf', 'Bread', 6.50, 2.80, 14, '🍞', ['#d4a373', '#a47148']],
            ['Whole Wheat Bread', 'Bread', 4.80, 2.00, 22, '🍞', ['#bc8a5f', '#8b5e34']],
            ['French Baguette', 'Bread', 2.80, 1.00, 30, '🥖', ['#e9c46a', '#d4a24c']],
            ['Almond Croissant', 'Pastries', 3.20, 1.30, 26, '🥐', ['#f4a261', '#e76f51']],
            ['Pain au Chocolat', 'Pastries', 3.00, 1.20, 28, '🥐', ['#9c6644', '#582f0e']],
            ['Cinnamon Roll', 'Pastries', 3.40, 1.30, 20, '🥮', ['#ddb892', '#b08968']],
            ['Oatmeal Raisin Cookie', 'Cookies', 1.60, 0.55, 45, '🍪', ['#cdb4a0', '#a98467']],
            ['Double Chocolate Cookie', 'Cookies', 1.80, 0.60, 40, '🍪', ['#6f4e37', '#3d2314']],
            ['Iced Latte', 'Drinks', 3.50, 1.10, 60, '🥤', ['#b08968', '#7f5539']],
            ['Hot Cappuccino', 'Drinks', 3.20, 1.00, 60, '☕', ['#a68a64', '#6f4e37']],
            ['Fresh Orange Juice', 'Drinks', 3.00, 1.20, 35, '🍊', ['#ffb703', '#fb8500']],
            ['Chicken Sandwich', 'Sandwiches', 5.50, 2.60, 15, '🥪', ['#90be6d', '#43aa8b']],
            ['Tuna Melt', 'Sandwiches', 5.80, 2.80, 12, '🥪', ['#4d908e', '#277da1']],
            ['Pumpkin Spice Muffin', 'Seasonal', 2.90, 1.10, 22, '🧁', ['#f3722c', '#f8961e']],
        ];

        $storage = app(ProductImageStorage::class);
        $disk = $storage->disk();
        $out = [];

        foreach ($defs as $i => [$name, $cat, $price, $cost, $stock, $emoji, $colors]) {
            $sku = 'DEMO-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);
            $product = Product::where('sku', $sku)->first();

            if (!$product) {
                $product = Product::create([
                    'category_id' => $cats[$cat]->id,
                    'name' => $name,
                    'sku' => $sku,
                    'description' => $name . ' - baked fresh daily (demo item).',
                    'price' => $price,
                    'cost' => $cost,
                    'stock' => $stock,
                    'minimum_stock' => 5,
                    'status' => 'active',
                    'image_emoji' => $emoji,
                ]);
            }

            // Generate the image once; keep an existing, still-present file.
            $current = (string) $product->image_path;
            if ($current === '' || !$disk->exists($storage->mediaKey($current) ?? $current)) {
                $file = 'demo_' . strtolower($sku) . '.png';
                $disk->put('products/' . $file, $this->makeImage($name, $colors[0], $colors[1]), ['visibility' => 'public']);
                $product->image_path = 'products/' . $file;
                $product->save();
            }

            $out[] = $product;
        }

        return $out;
    }

    private function makeImage(string $label, string $c1, string $c2): string
    {
        $w = 600;
        $h = 600;
        $im = imagecreatetruecolor($w, $h);
        [$r1, $g1, $b1] = sscanf($c1, '#%02x%02x%02x');
        [$r2, $g2, $b2] = sscanf($c2, '#%02x%02x%02x');

        for ($y = 0; $y < $h; $y++) {
            $t = $y / ($h - 1);
            $col = imagecolorallocate($im, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t));
            imageline($im, 0, $y, $w, $y, $col);
        }

        imagealphablending($im, true);
        $soft = imagecolorallocatealpha($im, 255, 255, 255, 96);
        $plate = imagecolorallocatealpha($im, 255, 255, 255, 40);
        imagefilledellipse($im, 470, 120, 260, 260, $soft);
        imagefilledellipse($im, 110, 520, 200, 200, $soft);
        imagefilledellipse($im, 300, 270, 330, 330, $plate);
        imagefilledellipse($im, 300, 270, 250, 250, $soft);

        $white = imagecolorallocate($im, 255, 255, 255);
        $shadow = imagecolorallocatealpha($im, 0, 0, 0, 90);
        $font = $this->font();
        $lines = explode("\n", wordwrap($label, 16, "\n", true));

        if ($font && function_exists('imagettftext')) {
            $size = 34;
            $y = 470;
            foreach ($lines as $line) {
                $box = imagettfbbox($size, 0, $font, $line);
                $x = (int) (($w - ($box[2] - $box[0])) / 2);
                imagettftext($im, $size, 0, $x + 2, $y + 2, $shadow, $font, $line);
                imagettftext($im, $size, 0, $x, $y, $white, $font, $line);
                $y += 50;
            }
            $initial = mb_strtoupper(mb_substr($label, 0, 1));
            $box = imagettfbbox(120, 0, $font, $initial);
            imagettftext($im, 120, 0, (int) (($w - ($box[2] - $box[0])) / 2), 315, $white, $font, $initial);
        } else {
            $y = 450;
            foreach ($lines as $line) {
                imagestring($im, 5, (int) (($w - imagefontwidth(5) * strlen($line)) / 2), $y, $line, $white);
                $y += 24;
            }
        }

        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();
        imagedestroy($im);

        return $png;
    }

    private function font(): ?string
    {
        foreach ([
            'C:\\Windows\\Fonts\\segoeuib.ttf',
            'C:\\Windows\\Fonts\\arialbd.ttf',
            'C:\\Windows\\Fonts\\arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    // ── Suppliers, ingredients, recipes ──
    private function suppliersAndIngredients(array $products): void
    {
        $s1 = Supplier::firstOrCreate(['name' => 'Golden Grain Mills'], ['phone' => '+855 12 555 101', 'email' => 'sales@goldengrain.test', 'address' => 'Kandal Province, Cambodia', 'status' => 'active']);
        $s2 = Supplier::firstOrCreate(['name' => 'Fresh Farm Dairy'], ['phone' => '+855 17 555 202', 'email' => 'orders@freshfarm.test', 'address' => 'Kampong Cham, Cambodia', 'status' => 'active']);

        $ing = [];
        foreach ([
            ['Almond Flour', $s1, 'kg', 18, 8.50, 4],
            ['Brown Sugar', $s1, 'kg', 30, 1.10, 8],
            ['Cocoa Powder', $s1, 'kg', 12, 7.20, 3],
            ['Heavy Cream', $s2, 'liter', 20, 3.80, 5],
            ['Cream Cheese', $s2, 'kg', 14, 6.40, 4],
            ['Vanilla Extract', $s1, 'liter', 4, 22.00, 1],
        ] as [$name, $sup, $unit, $qty, $cost, $min]) {
            $ing[$name] = Ingredient::firstOrCreate(
                ['name' => $name],
                ['supplier_id' => $sup->id, 'unit' => $unit, 'quantity' => $qty, 'cost' => $cost, 'minimum_quantity' => $min, 'status' => 'active']
            );
        }

        foreach ([
            0 => ['Strawberry Shortcake Recipe', 'Layer sponge with cream and strawberries.', ['Heavy Cream' => [0.20, 'liter'], 'Brown Sugar' => [0.10, 'kg']]],
            7 => ['Almond Croissant Recipe', 'Fill croissants with almond cream and bake.', ['Almond Flour' => [0.06, 'kg'], 'Heavy Cream' => [0.03, 'liter']]],
            11 => ['Double Chocolate Cookie Recipe', 'Mix cocoa dough with chips and bake.', ['Cocoa Powder' => [0.04, 'kg'], 'Brown Sugar' => [0.05, 'kg']]],
            2 => ['Tiramisu Cup Recipe', 'Layer mascarpone cream with coffee-soaked sponge.', ['Cream Cheese' => [0.10, 'kg'], 'Vanilla Extract' => [0.01, 'liter']]],
        ] as $idx => [$rname, $instructions, $lines]) {
            $recipe = Recipe::firstOrCreate(
                ['name' => $rname],
                ['product_id' => $products[$idx]->id, 'description' => 'Demo recipe', 'instructions' => $instructions, 'status' => 'active']
            );
            foreach ($lines as $iname => [$q, $unit]) {
                RecipeIngredient::firstOrCreate(
                    ['recipe_id' => $recipe->id, 'ingredient_id' => $ing[$iname]->id],
                    ['quantity' => $q, 'unit' => $unit]
                );
            }
        }
    }

    // ── Customers ──
    private function customers(): array
    {
        $out = [];
        foreach ([
            ['Sokha Chan', '+855 12 300 111', 'sokha.demo@example.test', 'Street 63, Phnom Penh'],
            ['Dara Phon', '+855 17 300 222', 'dara.demo@example.test', 'Toul Kork, Phnom Penh'],
            ['Chantrea Lim', '+855 96 300 333', 'chantrea.demo@example.test', 'Daun Penh, Phnom Penh'],
            ['Vannak Sao', '+855 15 300 444', 'vannak.demo@example.test', 'Siem Reap'],
            ['Sreymom Kem', '+855 10 300 555', 'sreymom.demo@example.test', 'Battambang'],
            ['Rithy Meas', '+855 78 300 666', 'rithy.demo@example.test', 'Chamkarmon, Phnom Penh'],
            ['Bopha Ly', '+855 11 300 777', 'bopha.demo@example.test', 'Sihanoukville'],
            ['Piseth Ouk', '+855 92 300 888', 'piseth.demo@example.test', 'Russey Keo, Phnom Penh'],
        ] as [$name, $phone, $email, $addr]) {
            $out[] = Customer::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'phone' => $phone, 'address' => $addr, 'loyalty_points' => 0, 'loyalty_tier' => 'standard', 'status' => 'active']
            );
        }

        return $out;
    }

    // ── Orders (last 30 days) with items, payments and sales ──
    private function orders(array $products, array $customers): int
    {
        // Only add orders once: skip when demo orders already exist.
        if (Order::where('customer_name', 'like', '%(demo)')->exists() || Order::where('special_instructions', 'DEMO')->exists()) {
            return 0;
        }

        $user = User::where('role', 'cashier')->first() ?? User::where('role', 'admin')->first() ?? User::first();
        if (!$user) {
            return 0;
        }

        mt_srand(20261110);
        $methods = ['cash', 'qr_code', 'card'];
        $count = 0;

        DB::transaction(function () use ($products, $customers, $user, $methods, &$count) {
            for ($n = 0; $n < 28; $n++) {
                $when = Carbon::now()->subDays(mt_rand(0, 29))->setTime(mt_rand(7, 19), mt_rand(0, 59), mt_rand(0, 59));
                $customer = mt_rand(0, 2) === 0 ? $customers[array_rand($customers)] : null;
                $method = $methods[array_rand($methods)];

                $lines = [];
                $subtotal = 0;
                foreach ((array) array_rand($products, mt_rand(1, 4)) as $idx) {
                    $p = $products[$idx];
                    $qty = mt_rand(1, 3);
                    $line = round($qty * (float) $p->price, 2);
                    $subtotal += $line;
                    $lines[] = [$p, $qty, $line];
                }
                $tax = round($subtotal * 0.10, 2);
                $total = round($subtotal + $tax, 2);

                $order = new Order([
                    'user_id' => $user->id,
                    'customer_id' => $customer?->id,
                    'order_number' => 'ORD-' . strtoupper(uniqid()) . $n,
                    'customer_name' => $customer?->name ?? 'Walk-in Customer',
                    'subtotal' => $subtotal,
                    'discount' => 0,
                    'tax' => $tax,
                    'total' => $total,
                    'payment_method' => $method,
                    'payment_status' => 'paid',
                    'order_status' => 'completed',
                    'special_instructions' => 'DEMO',
                ]);
                $order->created_at = $when;
                $order->updated_at = $when;
                $order->save();

                foreach ($lines as [$p, $qty, $line]) {
                    $oi = new OrderItem(['order_id' => $order->id, 'product_id' => $p->id, 'quantity' => $qty, 'price' => $p->price, 'subtotal' => $line]);
                    $oi->created_at = $when;
                    $oi->updated_at = $when;
                    $oi->save();
                }

                $pay = new Payment([
                    'order_id' => $order->id, 'user_id' => $user->id, 'payment_method' => $method, 'amount' => $total,
                    'payment_status' => 'completed', 'payment_reference' => 'PAY-' . strtoupper(uniqid()) . $n,
                    'payment_date' => $when, 'notes' => 'Demo POS sale',
                ]);
                $pay->created_at = $when;
                $pay->updated_at = $when;
                $pay->save();

                $sale = new Sale(['order_id' => $order->id, 'user_id' => $user->id, 'total' => $total, 'payment_method' => $method, 'payment_status' => 'paid', 'sold_at' => $when]);
                $sale->created_at = $when;
                $sale->updated_at = $when;
                $sale->save();

                $count++;
            }
        });

        return $count;
    }
}
