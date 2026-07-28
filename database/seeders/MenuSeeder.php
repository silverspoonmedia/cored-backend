<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\Role;
use App\Domain\Navigation\Models\Menu;
use App\Domain\Navigation\Repositories\Contracts\MenuRepositoryInterface;
use App\Domain\Navigation\Support\MenuLayout;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds the default admin web navigation that the dynamic sidebar renders.
 *
 * Icons are full Bootstrap Icons classes (e.g. "bi bi-people"); `route` holds
 * registered WEB route names. Menu names are stored as Spatie translatable JSON.
 */
class MenuSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Existing installs: upgrade flat vTual leaf into parent + children (idempotent).
        if (Menu::query()->exists()) {
            $this->ensureVtualMenuBranch();

            return;
        }

        $managementRoleIds = Role::query()
            ->where('guard_name', 'api')
            ->whereIn('name', ['Root', 'Admin', 'Moderator', 'Management'])
            ->pluck('id')
            ->all();

        $main = Menu::query()->create([
            'parent_id' => null,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Menu', 'id' => 'Menu', 'ja' => 'メニュー'],
            'icon' => null,
            'route' => null,
            'type' => 'h',
            'order' => 1,
            'is_authenticate' => true,
        ]);

        Menu::query()->create([
            'parent_id' => $main->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Dashboard', 'id' => 'Beranda', 'ja' => 'ダッシュボード'],
            'icon' => 'bi bi-speedometer2',
            'route' => 'dashboard',
            'type' => 'p',
            'order' => 1,
            'is_authenticate' => true,
        ]);

        $usersItem = Menu::query()->create([
            'parent_id' => $main->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'User management', 'id' => 'Manajemen Pengguna', 'ja' => 'ユーザー管理'],
            'icon' => 'bi bi-people',
            'route' => 'users.index',
            'type' => 'p',
            'order' => 2,
            'is_authenticate' => true,
        ]);
        $usersItem->roles()->sync($managementRoleIds);

        // Notifications: every authenticated user may receive personal notifications,
        // so no role gating — visible to all signed-in viewers.
        Menu::query()->create([
            'parent_id' => $main->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Notifications', 'id' => 'Notifikasi', 'ja' => '通知'],
            'icon' => 'bi bi-bell',
            'route' => 'notifications.index',
            'type' => 'p',
            'order' => 3,
            'is_authenticate' => true,
        ]);

        // Service catalog (sellable SaaS services + checkout). Visible to any
        // authenticated viewer with view service (User role has it).
        Menu::query()->create([
            'parent_id' => $main->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Services', 'id' => 'Layanan', 'ja' => 'サービス'],
            'icon' => 'bi bi-box-seam',
            'route' => 'services.index',
            'type' => 'p',
            'order' => 4,
            'is_authenticate' => true,
        ]);

        // Product surfaces (presentation only — not config/domains.php registry).
        // Fresh seed only; existing DBs keep prior tree until menus are re-seeded.
        $products = Menu::query()->create([
            'parent_id' => null,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Products', 'id' => 'Produk', 'ja' => 'プロダクト'],
            'icon' => null,
            'route' => null,
            'type' => 'h',
            'order' => 2,
            'is_authenticate' => true,
        ]);

        // File Hosting: no extra menu RBAC gate — permission middleware on the page.
        Menu::query()->create([
            'parent_id' => $products->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'My Files', 'id' => 'File Saya', 'ja' => 'マイファイル'],
            'icon' => 'bi bi-folder2-open',
            'route' => 'file-hosting.index',
            'type' => 'p',
            'order' => 1,
            'is_authenticate' => true,
        ]);

        // vTual: parent + children (hidden when product activation off via menu_routes).
        $this->seedVtualBranchUnder($products->id, 2);

        $admin = Menu::query()->create([
            'parent_id' => null,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Administration', 'id' => 'Administrasi', 'ja' => '管理'],
            'icon' => null,
            'route' => null,
            'type' => 'h',
            'order' => 3,
            'is_authenticate' => true,
        ]);

        $adminRoleIds = Role::query()
            ->where('guard_name', 'api')
            ->whereIn('name', ['Root', 'Admin'])
            ->pluck('id')
            ->all();

        $activityLogItem = Menu::query()->create([
            'parent_id' => $admin->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Activity log', 'id' => 'Log Aktivitas', 'ja' => 'アクティビティログ'],
            'icon' => 'bi bi-journal-text',
            'route' => 'activity-logs.index',
            'type' => 'p',
            'order' => 1,
            'is_authenticate' => true,
        ]);
        $activityLogItem->roles()->sync($adminRoleIds);

        $errorLogItem = Menu::query()->create([
            'parent_id' => $admin->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Error log', 'id' => 'Log Error', 'ja' => 'エラーログ'],
            'icon' => 'bi bi-bug',
            'route' => 'error-logs.index',
            'type' => 'p',
            'order' => 2,
            'is_authenticate' => true,
        ]);
        $errorLogItem->roles()->sync($adminRoleIds);

        $vtualCredentialsItem = Menu::query()->create([
            'parent_id' => $admin->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => [
                'en' => 'vTual platform credentials',
                'id' => 'Kredensial platform vTual',
                'ja' => 'vTual プラットフォーム認証情報',
            ],
            'icon' => 'bi bi-key',
            'route' => 'vtual.admin.credentials.index',
            'type' => 'p',
            'order' => 6,
            'is_authenticate' => true,
        ]);
        $vtualCredentialsItem->roles()->sync($adminRoleIds);

        $domainAccessItem = Menu::query()->create([
            'parent_id' => $admin->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Product domain access', 'id' => 'Akses Domain Produk', 'ja' => 'プロダクトドメインアクセス'],
            'icon' => 'bi bi-shield-lock',
            'route' => 'domain-access.index',
            'type' => 'p',
            'order' => 7,
            'is_authenticate' => true,
        ]);
        $domainAccessItem->roles()->sync($adminRoleIds);

        $productActivationsItem = Menu::query()->create([
            'parent_id' => $admin->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Product activations', 'id' => 'Aktivasi Produk', 'ja' => 'プロダクト有効化'],
            'icon' => 'bi bi-toggle-on',
            'route' => 'product-activations.index',
            'type' => 'p',
            'order' => 4,
            'is_authenticate' => true,
        ]);
        $productActivationsItem->roles()->sync($managementRoleIds);

        $paymentViewerRoleIds = Role::query()
            ->where('guard_name', 'api')
            ->whereIn('name', ['Root', 'Admin', 'Moderator', 'Management'])
            ->pluck('id')
            ->all();

        $payments = Menu::query()->create([
            'parent_id' => $admin->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Payments', 'id' => 'Pembayaran', 'ja' => '決済'],
            'icon' => 'bi bi-credit-card',
            'route' => null,
            'type' => 'p',
            'order' => 3,
            'is_authenticate' => true,
        ]);
        $payments->roles()->sync($paymentViewerRoleIds);

        $paymentChannelsItem = Menu::query()->create([
            'parent_id' => $payments->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Payment channels', 'id' => 'Channel pembayaran', 'ja' => '決済チャネル'],
            'icon' => 'bi bi-circle',
            'route' => 'payment-channels.index',
            'type' => 'c',
            'order' => 1,
            'is_authenticate' => true,
        ]);
        $paymentChannelsItem->roles()->sync($paymentViewerRoleIds);

        $paymentGatewaysItem = Menu::query()->create([
            'parent_id' => $payments->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Payment gateways', 'id' => 'Payment gateway', 'ja' => '決済ゲートウェイ'],
            'icon' => 'bi bi-circle',
            'route' => 'payment-gateways.index',
            'type' => 'c',
            'order' => 2,
            'is_authenticate' => true,
        ]);
        $paymentGatewaysItem->roles()->sync($paymentViewerRoleIds);

        $invoicesItem = Menu::query()->create([
            'parent_id' => $payments->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Invoices', 'id' => 'Invoice', 'ja' => '請求書'],
            'icon' => 'bi bi-circle',
            'route' => 'invoices.index',
            'type' => 'c',
            'order' => 3,
            'is_authenticate' => true,
        ]);
        $invoicesItem->roles()->sync($paymentViewerRoleIds);

        $subscriptionsItem = Menu::query()->create([
            'parent_id' => $payments->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Subscriptions', 'id' => 'Langganan', 'ja' => 'サブスクリプション'],
            'icon' => 'bi bi-circle',
            'route' => 'subscriptions.index',
            'type' => 'c',
            'order' => 4,
            'is_authenticate' => true,
        ]);
        $subscriptionsItem->roles()->sync($paymentViewerRoleIds);

        $menus = Menu::query()->create([
            'parent_id' => $admin->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Navigation menu', 'id' => 'Navigasi Menu', 'ja' => 'ナビゲーションメニュー'],
            'icon' => 'bi bi-list-nested',
            'route' => null,
            'type' => 'p',
            'order' => 5,
            'is_authenticate' => true,
        ]);
        $menus->roles()->sync($managementRoleIds);

        Menu::query()->create([
            'parent_id' => $menus->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Menu list', 'id' => 'Daftar Menu', 'ja' => 'メニュー一覧'],
            'icon' => 'bi bi-circle',
            'route' => 'menus.index',
            'type' => 'c',
            'order' => 1,
            'is_authenticate' => true,
        ]);

        Menu::query()->create([
            'parent_id' => $menus->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Trashed menus', 'id' => 'Menu Terhapus', 'ja' => '削除済みメニュー'],
            'icon' => 'bi bi-trash',
            'route' => 'menus.trashed',
            'type' => 'c',
            'order' => 2,
            'is_authenticate' => true,
        ]);

        // Top navbar shortcuts (layout=navbar). Headers flatten in the bar renderer.
        Menu::query()->create([
            'parent_id' => null,
            'layout' => MenuLayout::NAVBAR,
            'name' => ['en' => 'Home', 'id' => 'Beranda', 'ja' => 'ホーム'],
            'icon' => 'bi bi-house',
            'route' => 'dashboard',
            'type' => 'p',
            'order' => 1,
            'is_authenticate' => true,
        ]);

        app(MenuRepositoryInterface::class)->flushNavigationCache();
    }

    /**
     * Fresh install: create vTual parent under Products with overview / claim / registry children.
     */
    private function seedVtualBranchUnder(string $productsId, int $order): void
    {
        $staffRoleIds = Role::query()
            ->where('guard_name', 'api')
            ->whereIn('name', ['Root', 'Admin', 'Moderator'])
            ->pluck('id')
            ->all();

        $parent = Menu::query()->create([
            'parent_id' => $productsId,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'vTual', 'id' => 'vTual', 'ja' => 'vTual'],
            'icon' => 'bi bi-broadcast',
            'route' => null,
            'type' => 'p',
            'order' => $order,
            'is_authenticate' => true,
        ]);

        $this->createVtualChildren($parent->id, $staffRoleIds);
    }

    /**
     * Existing installs: convert flat vTual leaf (route=vtual.index) into parent + children.
     * Idempotent — safe to re-run.
     */
    private function ensureVtualMenuBranch(): void
    {
        $staffRoleIds = Role::query()
            ->where('guard_name', 'api')
            ->whereIn('name', ['Root', 'Admin', 'Moderator'])
            ->pluck('id')
            ->all();

        // Already has claim child → branch present; ensure newer children (idempotent add).
        if (Menu::query()->where('route', 'vtual.claims.index')->exists()) {
            $registry = Menu::query()->where('route', 'vtual.claims.staff')->first();
            if ($registry !== null && $staffRoleIds !== []) {
                $registry->roles()->sync($staffRoleIds);
            }

            $parent = Menu::query()->where('route', 'vtual.claims.index')->first()?->parent_id;
            if ($parent !== null) {
                $this->createVtualChildren($parent, $staffRoleIds);
            }

            app(MenuRepositoryInterface::class)->flushNavigationCache();

            return;
        }

        $leaf = Menu::query()
            ->where('route', 'vtual.index')
            ->where('type', 'p')
            ->first();

        if ($leaf === null) {
            $products = Menu::query()
                ->whereNull('parent_id')
                ->where('type', 'h')
                ->where('layout', MenuLayout::SIDEBAR)
                ->get()
                ->first(fn (Menu $m) => $m->getTranslation('name', 'en') === 'Products');

            if ($products === null) {
                return;
            }

            $this->seedVtualBranchUnder($products->id, 2);
            app(MenuRepositoryInterface::class)->flushNavigationCache();

            return;
        }

        // Promote leaf to parent (no direct route; children hold destinations).
        $leaf->forceFill([
            'route' => null,
            'icon' => $leaf->icon ?: 'bi bi-broadcast',
        ])->save();

        $this->createVtualChildren($leaf->id, $staffRoleIds);
        app(MenuRepositoryInterface::class)->flushNavigationCache();
    }

    /**
     * @param  list<string|int>  $staffRoleIds
     */
    private function createVtualChildren(string $parentId, array $staffRoleIds): void
    {
        $children = [
            [
                'name' => ['en' => 'Overview', 'id' => 'Ringkasan', 'ja' => '概要'],
                'icon' => 'bi bi-house',
                'route' => 'vtual.index',
                'order' => 1,
                'staff_only' => false,
            ],
            [
                'name' => ['en' => 'My profiles', 'id' => 'Profil saya', 'ja' => 'マイプロフィール'],
                'icon' => 'bi bi-person-badge',
                'route' => 'vtual.profiles.index',
                'order' => 2,
                'staff_only' => false,
            ],
            [
                'name' => ['en' => 'Claim as vTuber', 'id' => 'Klaim sebagai vTuber', 'ja' => 'vTuber としてクレーム'],
                'icon' => 'bi bi-person-check',
                'route' => 'vtual.claims.index',
                'order' => 3,
                'staff_only' => false,
            ],
            [
                'name' => ['en' => 'Registry & pending', 'id' => 'Registry & pending', 'ja' => 'レジストリと保留'],
                'icon' => 'bi bi-inbox',
                'route' => 'vtual.claims.staff',
                'order' => 4,
                'staff_only' => true,
            ],
        ];

        // Update existing first (high order → low) so unique (parent, type, order) never clashes
        // when inserting a new sibling into a middle slot (e.g. My profiles at order 2).
        $existingChildren = [];
        $missingChildren = [];
        foreach ($children as $child) {
            if (Menu::query()->where('route', $child['route'])->exists()) {
                $existingChildren[] = $child;
            } else {
                $missingChildren[] = $child;
            }
        }

        usort($existingChildren, static fn (array $a, array $b): int => $b['order'] <=> $a['order']);

        foreach (array_merge($existingChildren, $missingChildren) as $child) {
            $existing = Menu::query()->where('route', $child['route'])->first();
            if ($existing !== null) {
                $existing->forceFill([
                    'parent_id' => $parentId,
                    'layout' => MenuLayout::SIDEBAR,
                    'name' => $child['name'],
                    'icon' => $child['icon'],
                    'type' => 'c',
                    'order' => $child['order'],
                    'is_authenticate' => true,
                ])->save();
                $item = $existing;
            } else {
                $item = Menu::query()->create([
                    'parent_id' => $parentId,
                    'layout' => MenuLayout::SIDEBAR,
                    'name' => $child['name'],
                    'icon' => $child['icon'],
                    'route' => $child['route'],
                    'type' => 'c',
                    'order' => $child['order'],
                    'is_authenticate' => true,
                ]);
            }

            if ($child['staff_only'] && $staffRoleIds !== []) {
                $item->roles()->sync($staffRoleIds);
            }
        }
    }
}
