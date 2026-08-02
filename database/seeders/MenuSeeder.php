<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\Role;
use App\Domain\Navigation\Models\Menu;
use App\Domain\Navigation\Repositories\Contracts\MenuRepositoryInterface;
use App\Domain\Navigation\Support\MenuLayout;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the canonical dynamic navigation tree.
 *
 * This seeder is intentionally authoritative: every run replaces all active and
 * trashed menu rows so existing installations converge on the same UX tree.
 */
class MenuSeeder extends Seeder
{
    use WithoutModelEvents;

    /** @var array<string, string> */
    private array $roleIds = [];

    public function run(): void
    {
        $this->roleIds = Role::query()
            ->where('guard_name', 'api')
            ->pluck('id', 'name')
            ->all();

        DB::transaction(function (): void {
            Menu::withTrashed()->forceDelete();

            $this->seedTree($this->sidebarTree(), MenuLayout::SIDEBAR);
            $this->seedTree($this->navbarTree(), MenuLayout::NAVBAR);
        });

        app(MenuRepositoryInterface::class)->flushNavigationCache();
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    private function seedTree(array $nodes, string $layout, ?string $parentId = null): void
    {
        foreach ($nodes as $index => $node) {
            $children = $node['children'] ?? [];
            $roleNames = $node['roles'] ?? [];

            $menu = Menu::query()->create([
                'parent_id' => $parentId,
                'layout' => $layout,
                'name' => $node['name'],
                'description' => $node['description'] ?? null,
                'icon' => $node['icon'] ?? null,
                'route' => $node['route'] ?? null,
                'type' => $node['type'],
                'order' => $index + 1,
                'is_authenticate' => $node['is_authenticate'] ?? true,
                'is_guest_only' => $node['is_guest_only'] ?? null,
            ]);

            if ($roleNames !== []) {
                $menu->roles()->sync($this->idsForRoles($roleNames));
            }

            if ($children !== []) {
                $this->seedTree($children, $layout, $menu->id);
            }
        }
    }

    /**
     * @param  list<string>  $roleNames
     * @return list<string>
     */
    private function idsForRoles(array $roleNames): array
    {
        return array_values(array_filter(array_map(
            fn (string $roleName): ?string => $this->roleIds[$roleName] ?? null,
            $roleNames,
        )));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sidebarTree(): array
    {
        $managementRoles = ['Root', 'Admin', 'Moderator', 'Management'];
        $adminRoles = ['Root', 'Admin'];

        return [
            [
                'name' => $this->translations('Workspace', 'Ruang Kerja', 'ワークスペース'),
                'type' => 'h',
                'children' => [
                    [
                        'name' => $this->translations('Dashboard', 'Beranda', 'ダッシュボード'),
                        'icon' => 'bi bi-speedometer2',
                        'route' => 'dashboard',
                        'type' => 'p',
                    ],
                    [
                        'name' => $this->translations('Notifications', 'Notifikasi', '通知'),
                        'icon' => 'bi bi-bell',
                        'route' => 'notifications.index',
                        'type' => 'p',
                    ],
                    [
                        'name' => $this->translations('Services', 'Layanan', 'サービス'),
                        'icon' => 'bi bi-box-seam',
                        'route' => 'services.index',
                        'type' => 'p',
                    ],
                ],
            ],
            [
                'name' => $this->translations('Products', 'Produk', 'プロダクト'),
                'type' => 'h',
                'children' => [
                    [
                        'name' => $this->translations('File Hosting', 'File Hosting', 'ファイルホスティング'),
                        'icon' => 'bi bi-cloud',
                        'route' => 'file-hosting.index',
                        'type' => 'p',
                        'children' => [
                            [
                                'name' => $this->translations('My Files', 'File Saya', 'マイファイル'),
                                'icon' => 'bi bi-folder2-open',
                                'route' => 'file-hosting.index',
                                'type' => 'c',
                            ],
                            [
                                'name' => $this->translations('Trash', 'Sampah', 'ゴミ箱'),
                                'icon' => 'bi bi-trash3',
                                'route' => 'file-hosting.trash',
                                'type' => 'c',
                            ],
                            [
                                'name' => $this->translations('DMCA Blocklist', 'Daftar Blokir DMCA', 'DMCA ブロックリスト'),
                                'icon' => 'bi bi-shield-x',
                                'route' => 'file-hosting.blocklist.index',
                                'type' => 'c',
                                'roles' => [...$managementRoles, 'File Hosting Admin'],
                            ],
                        ],
                    ],
                    [
                        'name' => $this->translations('vTual', 'vTual', 'vTual'),
                        'icon' => 'bi bi-broadcast',
                        'route' => 'vtual.index',
                        'type' => 'p',
                        'children' => [
                            [
                                'name' => $this->translations('Overview', 'Ringkasan', '概要'),
                                'icon' => 'bi bi-house',
                                'route' => 'vtual.index',
                                'type' => 'c',
                            ],
                            [
                                'name' => $this->translations('My profiles', 'Profil saya', 'マイプロフィール'),
                                'icon' => 'bi bi-person-badge',
                                'route' => 'vtual.profiles.index',
                                'type' => 'c',
                            ],
                            [
                                'name' => $this->translations('Claim as vTuber', 'Klaim sebagai vTuber', 'vTuber としてクレーム'),
                                'icon' => 'bi bi-person-check',
                                'route' => 'vtual.claims.index',
                                'type' => 'c',
                            ],
                            [
                                'name' => $this->translations('Registry & pending', 'Registri & tertunda', '登録・保留'),
                                'icon' => 'bi bi-inbox',
                                'route' => 'vtual.claims.staff',
                                'type' => 'c',
                                'roles' => [...$managementRoles, 'Vtual Admin'],
                            ],
                            [
                                'name' => $this->translations('Platform credentials', 'Kredensial platform', 'プラットフォーム認証情報'),
                                'icon' => 'bi bi-key',
                                'route' => 'vtual.admin.credentials.index',
                                'type' => 'c',
                                'roles' => $adminRoles,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => $this->translations('Administration', 'Administrasi', '管理'),
                'type' => 'h',
                'children' => [
                    [
                        'name' => $this->translations('Access control', 'Kontrol akses', 'アクセス管理'),
                        'icon' => 'bi bi-people',
                        'type' => 'p',
                        'roles' => $managementRoles,
                        'children' => [
                            [
                                'name' => $this->translations('User management', 'Manajemen Pengguna', 'ユーザー管理'),
                                'icon' => 'bi bi-people',
                                'route' => 'users.index',
                                'type' => 'c',
                                'roles' => $managementRoles,
                            ],
                            [
                                'name' => $this->translations('Product activations', 'Aktivasi Produk', 'プロダクト有効化'),
                                'icon' => 'bi bi-toggle-on',
                                'route' => 'product-activations.index',
                                'type' => 'c',
                                'roles' => $managementRoles,
                            ],
                            [
                                'name' => $this->translations('Product domain access', 'Akses Domain Produk', 'プロダクトドメインアクセス'),
                                'icon' => 'bi bi-shield-lock',
                                'route' => 'domain-access.index',
                                'type' => 'c',
                                'roles' => $adminRoles,
                            ],
                        ],
                    ],
                    [
                        'name' => $this->translations('Payments', 'Pembayaran', '決済'),
                        'icon' => 'bi bi-credit-card',
                        'type' => 'p',
                        'roles' => $managementRoles,
                        'children' => [
                            [
                                'name' => $this->translations('Payment income', 'Pendapatan pembayaran', '決済収入'),
                                'icon' => 'bi bi-graph-up-arrow',
                                'route' => 'payments.income.index',
                                'type' => 'c',
                                'roles' => $managementRoles,
                            ],
                            [
                                'name' => $this->translations('Invoices', 'Invoice', '請求書'),
                                'icon' => 'bi bi-receipt',
                                'route' => 'invoices.index',
                                'type' => 'c',
                                'roles' => $managementRoles,
                            ],
                            [
                                'name' => $this->translations('Subscriptions', 'Langganan', 'サブスクリプション'),
                                'icon' => 'bi bi-arrow-repeat',
                                'route' => 'subscriptions.index',
                                'type' => 'c',
                                'roles' => $managementRoles,
                            ],
                            [
                                'name' => $this->translations('Payment channels', 'Channel pembayaran', '決済チャネル'),
                                'icon' => 'bi bi-credit-card-2-front',
                                'route' => 'payment-channels.index',
                                'type' => 'c',
                                'roles' => $managementRoles,
                            ],
                            [
                                'name' => $this->translations('Payment gateways', 'Payment gateway', '決済ゲートウェイ'),
                                'icon' => 'bi bi-diagram-3',
                                'route' => 'payment-gateways.index',
                                'type' => 'c',
                                'roles' => $managementRoles,
                            ],
                        ],
                    ],
                    [
                        'name' => $this->translations('Inference', 'Inferensi', '推論'),
                        'icon' => 'bi bi-cpu',
                        'type' => 'p',
                        'roles' => $managementRoles,
                        'children' => [
                            [
                                'name' => $this->translations('Model catalog', 'Katalog model', 'モデルカタログ'),
                                'icon' => 'bi bi-boxes',
                                'route' => 'inference.model-cards.index',
                                'type' => 'c',
                                'roles' => $managementRoles,
                            ],
                            [
                                'name' => $this->translations('Providers', 'Provider', 'プロバイダー'),
                                'icon' => 'bi bi-hdd-network',
                                'route' => 'inference.providers.index',
                                'type' => 'c',
                                'roles' => $managementRoles,
                            ],
                            [
                                'name' => $this->translations('Usage events', 'Event penggunaan', '利用イベント'),
                                'icon' => 'bi bi-graph-up',
                                'route' => 'inference.usage-events.index',
                                'type' => 'c',
                                'roles' => $managementRoles,
                            ],
                        ],
                    ],
                    [
                        'name' => $this->translations('Monitoring', 'Pemantauan', '監視'),
                        'icon' => 'bi bi-activity',
                        'type' => 'p',
                        'roles' => $adminRoles,
                        'children' => [
                            [
                                'name' => $this->translations('Activity log', 'Log Aktivitas', 'アクティビティログ'),
                                'icon' => 'bi bi-journal-text',
                                'route' => 'activity-logs.index',
                                'type' => 'c',
                                'roles' => $adminRoles,
                            ],
                            [
                                'name' => $this->translations('Error log', 'Log Error', 'エラーログ'),
                                'icon' => 'bi bi-bug',
                                'route' => 'error-logs.index',
                                'type' => 'c',
                                'roles' => $adminRoles,
                            ],
                        ],
                    ],
                    [
                        'name' => $this->translations('Settings', 'Pengaturan', '設定'),
                        'icon' => 'bi bi-gear',
                        'type' => 'p',
                        'roles' => $adminRoles,
                        'children' => [
                            [
                                'name' => $this->translations('Platform', 'Platform', 'プラットフォーム'),
                                'icon' => 'bi bi-sliders',
                                'route' => 'settings.platform',
                                'type' => 'c',
                                'roles' => $adminRoles,
                            ],
                            [
                                'name' => $this->translations('Notification audiences', 'Audiens notifikasi', '通知オーディエンス'),
                                'icon' => 'bi bi-bell',
                                'route' => 'notification-audiences.index',
                                'type' => 'c',
                                'roles' => $adminRoles,
                            ],
                            [
                                'name' => $this->translations('Currencies', 'Mata uang', '通貨'),
                                'icon' => 'bi bi-currency-exchange',
                                'route' => 'currencies.index',
                                'type' => 'c',
                                'roles' => $adminRoles,
                            ],
                        ],
                    ],
                    [
                        'name' => $this->translations('Navigation', 'Navigasi', 'ナビゲーション'),
                        'icon' => 'bi bi-list-nested',
                        'type' => 'p',
                        'roles' => $managementRoles,
                        'children' => [
                            [
                                'name' => $this->translations('Menu list', 'Daftar Menu', 'メニュー一覧'),
                                'icon' => 'bi bi-list-ul',
                                'route' => 'menus.index',
                                'type' => 'c',
                                'roles' => $managementRoles,
                            ],
                            [
                                'name' => $this->translations('Trashed menus', 'Menu Terhapus', '削除済みメニュー'),
                                'icon' => 'bi bi-trash',
                                'route' => 'menus.trashed',
                                'type' => 'c',
                                'roles' => $managementRoles,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function navbarTree(): array
    {
        return [
            [
                'name' => $this->translations('Home', 'Beranda', 'ホーム'),
                'icon' => 'bi bi-house',
                'route' => 'dashboard',
                'type' => 'p',
            ],
        ];
    }

    /**
     * @return array{en: string, id: string, ja: string}
     */
    private function translations(string $english, string $indonesian, string $japanese): array
    {
        return [
            'en' => $english,
            'id' => $indonesian,
            'ja' => $japanese,
        ];
    }
}
