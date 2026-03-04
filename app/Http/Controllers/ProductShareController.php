<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductDetailResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductShareController extends Controller
{
    public function assetLinks(): JsonResponse
    {
        return response()->json([
            [
                'relation' => ['delegate_permission/common.handle_all_urls'],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => config('deep_links.android.package_name'),
                    'sha256_cert_fingerprints' => config('deep_links.android.sha256_cert_fingerprints', []),
                ],
            ],
        ]);
    }

    public function appleAppSiteAssociation(): JsonResponse
    {
        $teamId = (string) config('deep_links.ios.team_id', '');
        $bundleId = (string) config('deep_links.ios.bundle_id', '');

        return response()->json([
            'applinks' => [
                'apps' => [],
                'details' => [
                    [
                        'appID' => trim($teamId.'.'.$bundleId, '.'),
                        'paths' => ['/products/*'],
                    ],
                ],
            ],
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        $product = Product::with([
            'category',
            'vendor',
            'shop',
            'images',
            'variants',
            'tags',
        ])->where('slug', $slug)->firstOrFail();

        $productPayload = ProductDetailResource::make($product)->resolve($request);
        $imageUrls = $this->buildAbsoluteImageUrls($productPayload);

        $firstImage = $imageUrls->first();
        $description = Str::limit(
            strip_tags((string) Arr::get($productPayload, 'description', '')),
            160,
            ''
        );
        $shareBaseUrl = rtrim((string) config('deep_links.share_base_url', config('app.url')), '/');

        return view('products.share', [
            'product' => [
                'id' => $product->id,
                'name' => Arr::get($productPayload, 'name'),
                'description' => Arr::get($productPayload, 'description'),
                'image' => $firstImage,
                'price' => Arr::get($productPayload, 'discount_price')
                    ?? Arr::get($productPayload, 'price'),
                'currency' => Arr::get($productPayload, 'currency', 'GHS'),
            ],
            'openGraph' => [
                'title' => Arr::get($productPayload, 'name'),
                'description' => $description,
                'image' => $firstImage,
                'url' => $shareBaseUrl.'/products/'.$product->slug,
            ],
            'downloadLinks' => [
                'android' => (string) config('deep_links.android.store_url', ''),
                'ios' => (string) config('deep_links.ios.store_url', ''),
            ],
        ]);
    }

    /**
     * Legacy route: redirect integer ID URLs to slug-based URLs.
     */
    public function showById(int $id): RedirectResponse
    {
        $product = Product::findOrFail($id);

        return redirect()->route('products.share', ['slug' => $product->slug], 301);
    }

    /**
     * @param  array<string, mixed>  $productPayload
     */
    private function buildAbsoluteImageUrls(array $productPayload): Collection
    {
        /** @var array<int, string> $images */
        $images = Arr::get($productPayload, 'images', []);

        $imageUrls = collect($images)
            ->filter(fn ($url) => filled($url))
            ->map(fn ($url) => $this->toAbsoluteUrl((string) $url))
            ->values();

        if ($imageUrls->isEmpty() && filled(Arr::get($productPayload, 'thumbnail'))) {
            $imageUrls = collect([
                $this->toAbsoluteUrl((string) Arr::get($productPayload, 'thumbnail')),
            ]);
        }

        return $imageUrls;
    }

    private function toAbsoluteUrl(string $url): string
    {
        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return url($url);
    }
}
