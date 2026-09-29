<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\CouponWebsite;
use App\Support\BreadcrumbSchema;
use App\Support\CanonicalUrl;
use App\Support\ItemListSchema;
use App\Support\LithuanianDate;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CouponController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request)
    {
        $path = '/kuponai';
        $order = $request->string('order', 'best')->toString();
        $q = trim((string) $request->query('q', ''));

        $query = $this->baseActiveQuery();

        if ($q !== '') {
            $query->where(function ($inner) use ($q) {
                $inner->where('title', 'like', "%{$q}%")
                    ->orWhereHas('website', fn ($w) => $w->where('name', 'like', "%{$q}%"));
            });
        }

        $coupons = $this->applySort($query, $order)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $latestUpdate = Coupon::max('updated_at');
        $breadcrumbs = [['name' => 'Kuponai', 'href' => $path]];
        $websites = CouponWebsite::withCount(['coupons' => fn ($q) => $q->active()->currentlyValid()])
            ->having('coupons_count', '>', 0)
            ->orderByDesc('coupons_count')
            ->get();

        return view('kuponai.index', [
            'coupons' => $coupons,
            'websites' => $websites,
            'order' => $order,
            'q' => $q,
            'freshnessLabel' => $latestUpdate ? LithuanianDate::relative(Carbon::parse($latestUpdate)) : null,
            'canonical' => CanonicalUrl::build($path),
            'robots' => CanonicalUrl::robotsMeta($path),
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbSchema' => BreadcrumbSchema::build($breadcrumbs),
            // The shops with their own /kuponai/{slug} page, not the coupons
            // themselves — every coupon item used to carry the same /kuponai
            // URL, so the list said nothing a crawler could follow.
            'itemListSchema' => ItemListSchema::build(
                'Nuolaidų kodai ir kuponai pagal parduotuvę',
                $websites->map(fn (CouponWebsite $w) => ['name' => "{$w->name} nuolaidų kodai", 'href' => "/kuponai/{$w->slug}"])->all()
            ),
        ]);
    }

    public function hub(string $websiteSlug, Request $request)
    {
        $website = CouponWebsite::where('slug', $websiteSlug)->firstOrFail();
        $path = "/kuponai/{$websiteSlug}";
        $order = $request->string('order', 'best')->toString();

        $coupons = $this->applySort($this->baseActiveQuery()->where('website_id', $website->id), $order)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $breadcrumbs = [
            ['name' => 'Kuponai', 'href' => '/kuponai'],
            ['name' => $website->name, 'href' => $path],
        ];

        return view('kuponai.show', [
            'website' => $website,
            'coupons' => $coupons,
            'order' => $order,
            'canonical' => CanonicalUrl::build($path),
            'robots' => CanonicalUrl::robotsMeta($path),
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbSchema' => BreadcrumbSchema::build($breadcrumbs),
            'itemListSchema' => ItemListSchema::build(
                "{$website->name} nuolaidų kodai ir kuponai",
                $coupons->getCollection()->map(fn (Coupon $c) => ['name' => $c->title, 'href' => $path])->all(),
                $coupons->total()
            ),
        ]);
    }

    private function baseActiveQuery()
    {
        return Coupon::query()->with(['website', 'category'])->active()->currentlyValid();
    }

    private function applySort($query, string $order)
    {
        return match ($order) {
            'newest' => $query->orderByDesc('created_at'),
            'old' => $query->orderByRaw('valid_until IS NULL, valid_until ASC'),
            default => $query->orderBy('sort_order')->orderByDesc('created_at'),
        };
    }
}
