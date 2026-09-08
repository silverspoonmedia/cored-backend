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
            $this->seedTree($this->vtualTree(), MenuLayout::VTUAL);
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
                'hash' => $node['hash'] ?? null,
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
        $internalStaffRoles = config('vtual.internal_web_roles', ['Root', 'Admin', 'Moderator']);

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
                        'name' => $this->translations('Archivd', 'Archivd', 'Archivd'),
                        'icon' => 'bi bi-cloud',
                        'route' => 'archivd.index',
                        'type' => 'p',
                        'children' => [
                            [
                                'name' => $this->translations('My Files', 'File Saya', 'マイファイル'),
                                'icon' => 'bi bi-folder2-open',
                                'route' => 'archivd.index',
                                'type' => 'c',
                            ],
                            [
                                'name' => $this->translations('Trash', 'Sampah', 'ゴミ箱'),
                                'icon' => 'bi bi-trash3',
                                'route' => 'archivd.trash',
                                'type' => 'c',
                            ],
                            [
                                'name' => $this->translations('DMCA Blocklist', 'Daftar Blokir DMCA', 'DMCA ブロックリスト'),
                                'icon' => 'bi bi-shield-x',
                                'route' => 'archivd.blocklist.index',
                                'type' => 'c',
                                'roles' => [...$managementRoles, 'Archivd Admin'],
                            ],
                        ],
                    ],
                    [
                        'name' => $this->translations('vTual', 'vTual', 'vTual'),
                        'icon' => 'bi bi-broadcast',
                        'route' => 'vtual.profiles.index',
                        'type' => 'p',
                        'roles' => ['Vtual vTuber', 'Vtual Editor', 'Vtual Admin'],
                        'children' => [
                            [
                                'name' => $this->translations('My vTuber profiles', 'Profil vTuber saya', 'マイ vTuber プロフィール'),
                                'icon' => 'bi bi-person-badge',
                                'route' => 'vtual.profiles.index',
                                'type' => 'c',
                                'roles' => ['Vtual vTuber', 'Vtual Editor', 'Vtual Admin'],
                            ],
                            [
                                'name' => $this->translations('Managements', 'Manajemen', 'マネジメント'),
                                'icon' => 'bi bi-people',
                                'route' => 'vtual.managements.index',
                                'type' => 'c',
                                // Empty stored roles are intentional and ONLY safe
                                // because this route is in MenuRepository's
                                // MANAGEMENT_MEMBER_ROUTES: rule 7 falls through
                                // hasAnyRole([]) to the org-membership check, so
                                // only vTual management members see this node.
                                // Removing the route from that constant would turn
                                // this into an open node — do not do that.
                                'roles' => [],
                            ],
                            [
                                'name' => $this->translations('Events', 'Acara', 'イベント'),
                                'icon' => 'bi bi-calendar-event',
                                'route' => 'vtual.events.index',
                                'type' => 'c',
                                // Duplicate of the Managements node's rule-7
                                // pattern: empty stored roles + the route in
                                // MANAGEMENT_MEMBER_ROUTES scope visibility to
                                // org members (event submission is an org
                                // Owner/Admin power). Do not remove the route
                                // from that constant — that would open the node.
                                'roles' => [],
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
                        'name' => $this->translations('vTual', 'vTual', 'vTual'),
                        'icon' => 'bi bi-broadcast',
                        'route' => 'vtual.orphans.index',
                        'type' => 'p',
                        'roles' => $internalStaffRoles,
                        'children' => [
                            [
                                'name' => $this->translations('Orphan vTubers', 'vTuber yatim', '未クレーム vTuber'),
                                'icon' => 'bi bi-person-plus',
                                'route' => 'vtual.orphans.index',
                                'type' => 'c',
                                'roles' => $internalStaffRoles,
                            ],
                            [
                                'name' => $this->translations('Managements', 'Manajemen', 'マネジメント'),
                                'icon' => 'bi bi-people',
                                'route' => 'vtual.managements.index',
                                'type' => 'c',
                                // Staff-facing management administration (orgs are
                                // created here, then claimed by companies /
                                // agencies). Membership members never see this
                                // branch: the parent route is NOT in
                                // MANAGEMENT_MEMBER_ROUTES, so rule 7 fails on
                                // the ancestor chain.
                                'roles' => $adminRoles,
                            ],
                            [
                                'name' => $this->translations('Platform credentials', 'Kredensial platform', 'プラットフォーム認証情報'),
                                'icon' => 'bi bi-key',
                                'route' => 'vtual.platform-credentials.index',
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
                                'name' => $this->translations('Archivd', 'Archivd', 'Archivd'),
                                'icon' => 'bi bi-cloud',
                                'route' => 'settings.archivd',
                                'type' => 'c',
                                'roles' => $adminRoles,
                            ],
                            [
                                'name' => $this->translations('Microstock', 'Microstock', 'Microstock'),
                                'icon' => 'bi bi-images',
                                'route' => 'settings.microstock',
                                'type' => 'c',
                                'roles' => $adminRoles,
                            ],
                            [
                                'name' => $this->translations('vTual', 'vTual', 'vTual'),
                                'icon' => 'bi bi-broadcast',
                                'route' => 'settings.vtual',
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
     * Public vTual navigation tree (Valkivid3 template).
     *
     * Guest-visible nodes rendered by the SPA's VkStr1Layout header/mobile/footer.
     * No roles, not authenticate-only — visible to everyone including guests.
     */
    private function vtualTree(): array
    {
        return [
            [
                'name' => $this->translations('Home', 'Beranda', 'ホーム'),
                'icon' => 'bi bi-house',
                'route' => 'vtual.public',
                'type' => 'p',
                'is_authenticate' => false,
            ],
            [
                'name' => $this->translations('Directory', 'Direktori', 'ディレクトリ'),
                'icon' => 'bi bi-people',
                'route' => 'vtual.public',
                'hash' => '#vtubers',
                'type' => 'p',
                'is_authenticate' => false,
            ],
            [
                'name' => $this->translations('Contents', 'Konten', 'コンテンツ'),
                'icon' => 'bi bi-play-btn',
                'route' => 'vtual.public',
                'hash' => '#contents',
                'type' => 'p',
                'is_authenticate' => false,
            ],
        ];
    }

    /**
     * @return array{en: string, id: string, ja: string, ko: string, zh_CN: string, ar: string, ru: string}
     */
    private function translations(string $english, string $indonesian, string $japanese): array
    {
        $additional = [
            'Workspace' => [
                'ko' => '작업 공간',
                'zh_CN' => '工作区',
                'ar' => 'مساحة العمل',
                'ru' => 'Рабочая область',
            ],
            'Dashboard' => [
                'ko' => '대시보드',
                'zh_CN' => '控制面板',
                'ar' => 'لوحة التحكم',
                'ru' => 'Панель управления',
            ],
            'Notifications' => [
                'ko' => '알림',
                'zh_CN' => '通知',
                'ar' => 'الإشعارات',
                'ru' => 'Уведомления',
            ],
            'Services' => [
                'ko' => '서비스',
                'zh_CN' => '服务',
                'ar' => 'الخدمات',
                'ru' => 'Сервисы',
            ],
            'Products' => [
                'ko' => '제품',
                'zh_CN' => '产品',
                'ar' => 'المنتجات',
                'ru' => 'Продукты',
            ],
            'Archivd' => [
                'ko' => 'Archivd',
                'zh_CN' => 'Archivd',
                'ar' => 'Archivd',
                'ru' => 'Archivd',
            ],
            'My Files' => [
                'ko' => '내 파일',
                'zh_CN' => '我的文件',
                'ar' => 'ملفاتي',
                'ru' => 'Мои файлы',
            ],
            'Trash' => [
                'ko' => '휴지통',
                'zh_CN' => '回收站',
                'ar' => 'المهملات',
                'ru' => 'Корзина',
            ],
            'DMCA Blocklist' => [
                'ko' => 'DMCA 차단 목록',
                'zh_CN' => 'DMCA 黑名单',
                'ar' => 'قائمة حظر DMCA',
                'ru' => 'Список блокировки DMCA',
            ],
            'vTual' => [
                'ko' => 'vTual',
                'zh_CN' => 'vTual',
                'ar' => 'vTual',
                'ru' => 'vTual',
            ],
            'My vTuber profiles' => [
                'ko' => '내 vTuber 프로필',
                'zh_CN' => '我的 vTuber 资料',
                'ar' => 'ملفات vTuber الخاصة بي',
                'ru' => 'Мои профили vTuber',
            ],
            'Administration' => [
                'ko' => '관리',
                'zh_CN' => '管理',
                'ar' => 'الإدارة',
                'ru' => 'Администрирование',
            ],
            'Access control' => [
                'ko' => '접근 제어',
                'zh_CN' => '访问控制',
                'ar' => 'التحكم بالوصول',
                'ru' => 'Управление доступом',
            ],
            'User management' => [
                'ko' => '사용자 관리',
                'zh_CN' => '用户管理',
                'ar' => 'إدارة المستخدمين',
                'ru' => 'Управление пользователями',
            ],
            'Product activations' => [
                'ko' => '제품 활성화',
                'zh_CN' => '产品激活',
                'ar' => 'تفعيلات المنتجات',
                'ru' => 'Активации продуктов',
            ],
            'Product domain access' => [
                'ko' => '제품 도메인 접근',
                'zh_CN' => '产品域访问',
                'ar' => 'الوصول إلى نطاق المنتج',
                'ru' => 'Доступ к доменам продуктов',
            ],
            'Payments' => [
                'ko' => '결제',
                'zh_CN' => '支付',
                'ar' => 'المدفوعات',
                'ru' => 'Платежи',
            ],
            'Payment income' => [
                'ko' => '결제 수입',
                'zh_CN' => '支付收入',
                'ar' => 'إيرادات المدفوعات',
                'ru' => 'Доход от платежей',
            ],
            'Invoices' => [
                'ko' => '청구서',
                'zh_CN' => '发票',
                'ar' => 'الفواتير',
                'ru' => 'Счета',
            ],
            'Subscriptions' => [
                'ko' => '구독',
                'zh_CN' => '订阅',
                'ar' => 'الاشتراكات',
                'ru' => 'Подписки',
            ],
            'Payment channels' => [
                'ko' => '결제 채널',
                'zh_CN' => '支付渠道',
                'ar' => 'قنوات الدفع',
                'ru' => 'Платёжные каналы',
            ],
            'Payment gateways' => [
                'ko' => '결제 게이트웨이',
                'zh_CN' => '支付网关',
                'ar' => 'بوابات الدفع',
                'ru' => 'Платёжные шлюзы',
            ],
            'Inference' => [
                'ko' => '추론',
                'zh_CN' => '推理',
                'ar' => 'الاستدلال',
                'ru' => 'Инференс',
            ],
            'Model catalog' => [
                'ko' => '모델 카탈로그',
                'zh_CN' => '模型目录',
                'ar' => 'كتالوج النماذج',
                'ru' => 'Каталог моделей',
            ],
            'Providers' => [
                'ko' => '제공업체',
                'zh_CN' => '服务提供商',
                'ar' => 'المزوّدون',
                'ru' => 'Провайдеры',
            ],
            'Usage events' => [
                'ko' => '사용량 이벤트',
                'zh_CN' => '使用事件',
                'ar' => 'أحداث الاستخدام',
                'ru' => 'События использования',
            ],
            'Monitoring' => [
                'ko' => '모니터링',
                'zh_CN' => '监控',
                'ar' => 'المراقبة',
                'ru' => 'Мониторинг',
            ],
            'Activity log' => [
                'ko' => '활동 로그',
                'zh_CN' => '活动日志',
                'ar' => 'سجل النشاط',
                'ru' => 'Журнал действий',
            ],
            'Error log' => [
                'ko' => '오류 로그',
                'zh_CN' => '错误日志',
                'ar' => 'سجل الأخطاء',
                'ru' => 'Журнал ошибок',
            ],
            'Orphan vTubers' => [
                'ko' => '미등록 vTuber',
                'zh_CN' => '未认领的 vTuber',
                'ar' => 'vTubers غير المطالب بها',
                'ru' => 'Невостребованные vTuber',
            ],
            'Managements' => [
                'ko' => '매니지먼트',
                'zh_CN' => '管理机构',
                'ar' => 'جهات الإدارة',
                'ru' => 'Управляющие команды',
            ],
            'Events' => [
                'ko' => '이벤트',
                'zh_CN' => '活动',
                'ar' => 'الفعاليات',
                'ru' => 'События',
            ],
            'Platform credentials' => [
                'ko' => '플랫폼 자격 증명',
                'zh_CN' => '平台凭据',
                'ar' => 'بيانات اعتماد المنصة',
                'ru' => 'Учётные данные платформ',
            ],
            'Settings' => [
                'ko' => '설정',
                'zh_CN' => '设置',
                'ar' => 'الإعدادات',
                'ru' => 'Настройки',
            ],
            'Platform' => [
                'ko' => '플랫폼',
                'zh_CN' => '平台',
                'ar' => 'المنصة',
                'ru' => 'Платформа',
            ],
            'Microstock' => [
                'ko' => '마이크로스톡',
                'zh_CN' => 'Microstock',
                'ar' => 'Microstock',
                'ru' => 'Microstock',
            ],
            'Notification audiences' => [
                'ko' => '알림 대상',
                'zh_CN' => '通知受众',
                'ar' => 'جماهير الإشعارات',
                'ru' => 'Аудитории уведомлений',
            ],
            'Currencies' => [
                'ko' => '통화',
                'zh_CN' => '货币',
                'ar' => 'العملات',
                'ru' => 'Валюты',
            ],
            'Navigation' => [
                'ko' => '탐색',
                'zh_CN' => '导航',
                'ar' => 'التنقل',
                'ru' => 'Навигация',
            ],
            'Menu list' => [
                'ko' => '메뉴 목록',
                'zh_CN' => '菜单列表',
                'ar' => 'قائمة القوائم',
                'ru' => 'Список меню',
            ],
            'Trashed menus' => [
                'ko' => '삭제된 메뉴',
                'zh_CN' => '已删除菜单',
                'ar' => 'القوائم المحذوفة',
                'ru' => 'Удалённые меню',
            ],
            'Home' => [
                'ko' => '홈',
                'zh_CN' => '首页',
                'ar' => 'الرئيسية',
                'ru' => 'Главная',
            ],
            'Directory' => [
                'ko' => '디렉터리',
                'zh_CN' => '目录',
                'ar' => 'الدليل',
                'ru' => 'Каталог',
            ],
            'Contents' => [
                'ko' => '콘텐츠',
                'zh_CN' => '内容',
                'ar' => 'المحتوى',
                'ru' => 'Контент',
            ],
        ];

        $localized = $additional[$english] ?? [
            'ko' => $english,
            'zh_CN' => $english,
            'ar' => $english,
            'ru' => $english,
        ];

        return [
            'en' => $english,
            'id' => $indonesian,
            'ja' => $japanese,
            ...$localized,
        ];
    }
}
