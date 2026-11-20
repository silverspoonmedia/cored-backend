<?php

return [
    'gateways' => [
        'title' => 'بوابات الدفع',
        'list_title' => 'قائمة بوابات الدفع',
        'create' => 'إضافة بوابة',
        'create_title' => 'إنشاء بوابة دفع',
        'edit_title' => 'تعديل بوابة الدفع',
        'form' => [
            'name' => 'اسم النظام',
            'display_name' => 'اسم العرض',
            'priority' => 'الأولوية',
            'is_active' => 'نشطة',
        ],
        'confirm_delete' => 'هل تريد حذف بوابة الدفع هذه؟',
        'datatable' => [
            'empty' => 'لم يتم إعداد أي بوابات دفع.',
            // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
            'zero_records' => 'لا توجد بوابة دفع تطابق بحثك.',
            'columns' => [
                'name' => 'الاسم',
                'display_name' => 'اسم العرض',
                'is_active' => 'نشطة',
                'priority' => 'الأولوية',
            ],
        ],
    ],

    'show' => [
        'payment_not_started' => 'لم يتم اختيار طريقة الدفع بعد',
        'payment_pending' => 'بانتظار الدفع',
        'status' => [
            'paid' => 'مدفوعة',
            'expired' => 'منتهية الصلاحية',
            'cancelled' => 'ملغاة',
            'failed' => 'فشلت',
        ],
        'payment_steps' => [
            'aria' => 'خطوات الدفع',
            'choose_channel' => 'اختيار الطريقة',
            'choose_channel_hint' => 'اختر QRIS أو التحويل البنكي أو المحفظة الإلكترونية.',
            'pay' => 'دفع',
            'pay_hint' => 'أكمل الدفع باستخدام التعليمات المعروضة.',
            'confirm' => 'التأكيد',
            // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
            'confirm_hint' => 'تُحدَّث الحالة بعد أن يؤكد مزود الدفع العملية.',
        ],
    ],

    'invoices' => [
        'title' => 'الفواتير',
        'list_title' => 'قائمة الفواتير',
        'show_title' => 'تفاصيل الفاتورة',
        'show_card_title' => 'الفاتورة :number',
        'view_order' => 'عرض الطلب',
        'view_purchase' => 'عرض شراء الخدمة',
        'back_to_list' => 'العودة إلى الفواتير',
        'datatable' => [
            'empty' => 'لم يتم العثور على فواتير.',
            // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
            'zero_records' => 'لا توجد فاتورة تطابق بحثك.',
            'columns' => [
                'invoice_number' => 'رقم الفاتورة',
                'status' => 'الحالة',
                // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
                'subtotal' => 'المجموع الفرعي',
                'fee' => 'رسوم الدفع',
                'total' => 'إجمالي العميل',
                'created_at' => 'تاريخ الإنشاء',
                'actions' => 'الإجراءات',
            ],
        ],
        'export_csv' => 'تصدير CSV',
        'fields' => [
            'invoice_number' => 'رقم الفاتورة',
            'status' => 'الحالة',
            'payment_method' => 'طريقة الدفع',
            // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
            'gateway' => 'بوابة الدفع',
            'subtotal' => 'سعر الخدمة',
            'fee' => 'رسوم الدفع (المشتري)',
            'total' => 'إجمالي العميل',
            'currency' => 'العملة',
            'paid_at' => 'تاريخ الدفع',
            'expired_at' => 'تاريخ انتهاء الصلاحية',
            'created_at' => 'تاريخ الإنشاء',
            // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
            'payment_route' => 'مسار إتمام الشراء',
        ],
    ],

    'income' => [
        'title' => 'إيرادات الدفع',
        'subtitle' => 'الإيرادات والمبالغ المحصلة من الفواتير المدفوعة. سعر الخدمة هو دخل منتجك؛ ورسوم الدفع مبالغ إضافية يدفعها المشتري وتُحوّل إلى مزود الدفع.',
        'loading' => 'جارٍ التحميل…',
        'load_failed' => 'تعذر تحميل بيانات الدخل.',
        'empty_invoices' => 'لا توجد فواتير مدفوعة في هذه الفترة.',
        'empty_breakdown' => 'لا توجد بيانات لهذه الفترة.',
        'invoices_title' => 'الفواتير المدفوعة',
        'pagination' => 'الصفحة :page من :total',
        'filters' => [
            'from' => 'من',
            'to' => 'إلى',
            'basis' => 'أساس التاريخ',
            'basis_paid_at' => 'تاريخ الدفع (paid_at)',
            'basis_withdrawable_at' => 'تاريخ التسوية (withdrawable_at)',
            // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
            'gateway' => 'بوابة الدفع',
            // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
            'all_gateways' => 'كل بوابات الدفع',
            'apply' => 'تطبيق',
        ],
        'summary' => [
            'service_revenue' => 'إيرادات الخدمة',
            // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
            'service_revenue_hint' => 'مجموع المجاميع الفرعية للفواتير (مبيعات منتجاتك).',
            'buyer_fees' => 'رسوم الدفع على المشتري',
            // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
            'buyer_fees_hint' => 'رسوم إضافية على قنوات الدفع تُحصَّل من العملاء.',
            'cash_collected' => 'المبالغ المحصلة',
            // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
            'cash_collected_hint' => 'إجمالي ما دفعه العملاء (المجموع الفرعي + الرسوم).',
            'invoice_count' => 'الفواتير المدفوعة',
            'avg_order_value' => 'متوسط قيمة الطلب',
        ],
        'pipeline' => [
            'title' => 'مسار الأموال',
            'invoices' => 'فواتير',
            // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
            'awaiting_settlement' => 'بانتظار تسوية بوابة الدفع',
            'ready_to_disburse' => 'جاهزة للصرف',
            'in_payout' => 'قيد الصرف',
            'disbursed' => 'صُرفت إلى البنك',
            'failed_payout' => 'فشل الصرف',
        ],
        'breakdown' => [
            // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
            'gateway' => 'حسب بوابة الدفع',
            // curated at 2026-09-22T20:27:19+07:00 — ar curation wave
            'channel' => 'حسب قناة الدفع',
            'product' => 'حسب نوع المنتج',
            'label' => 'التسمية',
            'invoices' => 'الفواتير',
            'service_revenue' => 'إيرادات الخدمة',
            'cash_collected' => 'المبالغ المحصلة',
        ],
        'daily' => [
            'title' => 'الاتجاه اليومي',
            'date' => 'التاريخ',
            'invoices' => 'الفواتير',
            'service_revenue' => 'إيرادات الخدمة',
            'cash_collected' => 'المبالغ المحصلة',
        ],
    ],

    'subscriptions' => [
        'title' => 'الاشتراكات',
        'list_title' => 'قائمة الاشتراكات',
        'create_title' => 'إضافة اشتراك',
        'edit_title' => 'تعديل الاشتراك',
        'show_title' => 'تفاصيل الاشتراك',
        'show_card_title' => 'الاشتراك :number',
        'add_button' => 'إضافة اشتراك',
        'new_card_title' => 'اشتراك جديد',
        'edit_card_title' => 'تعديل الاشتراك: :number',
        'back_to_list' => 'العودة إلى الاشتراكات',
        'datatable' => [
            'empty' => 'لم يتم العثور على اشتراكات.',
            // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
            'zero_records' => 'لا يوجد اشتراك يطابق بحثك.',
            'columns' => [
                'subscription_number' => 'رقم الاشتراك',
                'subscriber_name' => 'المشترك',
                'plan_name' => 'الخطة',
                'status' => 'الحالة',
                'amount' => 'المبلغ',
                'actions' => 'الإجراءات',
            ],
        ],
        'fields' => [
            'subscriber_name' => 'اسم المشترك',
            'subscriber_email' => 'بريد المشترك الإلكتروني',
            'subscriber_phone' => 'هاتف المشترك',
            'plan_name' => 'اسم الخطة',
            'plan_description' => 'وصف الخطة',
            'billing_cycle' => 'دورة الفوترة',
            'amount' => 'المبلغ',
            'currency' => 'العملة',
            'status' => 'الحالة',
            'auto_renew' => 'التجديد التلقائي',
            'started_at' => 'تاريخ البدء',
            'expires_at' => 'تاريخ الانتهاء',
            'trial_ends_at' => 'تاريخ انتهاء الفترة التجريبية',
            'created_at' => 'تاريخ الإنشاء',
        ],
    ],

    'confirm' => [
        'delete_subscription' => 'هل تريد حذف هذا الاشتراك؟',
    ],

    'ipaymu_return' => [
        'invoice_not_found' => 'تعذر ربط عملية الدفع هذه بفاتورة.',
        'paid' => 'تم استلام الدفع. وُضعت فاتورتك في حالة مدفوعة.',
        // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
        'pending' => 'تجري معالجة الدفع. حدّث الصفحة بعد قليل إذا لم تُحدَّث الحالة.',
        'failed' => 'فشلت عملية الدفع أو رُفضت.',
        'cancelled' => 'أُلغيت عملية الدفع.',
        'expired' => 'انتهت صلاحية جلسة الدفع.',
    ],

    'checkout' => [
        'title' => 'الدفع',
        'invoice_summary' => 'الفاتورة :number',
        'amount' => 'المبلغ المستحق',
        'subtotal' => 'سعر الخدمة',
        'payment_fee' => 'رسوم الدفع',
        'payment_fee_note' => 'تُدفع لمزود الدفع وتُضاف إلى إجمالك.',
        'total_due' => 'الإجمالي المستحق',
        // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
        'fee_estimate_hint' => 'رسوم تقديرية؛ يُؤكَّد المبلغ النهائي عند المتابعة إلى الدفع.',
        'fee_percent_basis' => ':percent من سعر الخدمة',
        'fee_at_checkout_hint' => 'تُضاف رسوم مزود الدفع عند اختيار طريقة الدفع.',
        'routing_updated' => 'تم تحديث طرق الدفع. ربما تغيرت الرسوم.',
        'channel_unavailable' => 'لم يعد :channel متاحًا.',
        'fee_updated' => 'تم تحديث رسوم :channel من :old إلى :new.',
        'selected_channel_unavailable' => 'لم تعد طريقة الدفع التي اخترتها متاحة. اختر طريقة أخرى.',
        'pay_now' => 'ادفع الآن',
        'snap_loading' => 'جارٍ فتح الدفع…',
        // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
        'snap_failed' => 'تعذر بدء إتمام الشراء',
        // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
        'started' => 'تم إنشاء جلسة إتمام الشراء.',
        'paid' => 'هذه الفاتورة مدفوعة بالفعل.',
        'not_payable' => 'لا يمكن دفع هذه الفاتورة في حالتها الحالية.',
        // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
        'unsupported_gateway' => 'بوابة دفع الفاتورة لا تدعم إتمام الشراء المستضاف.',
        'select_channel' => 'طريقة الدفع',
        // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
        'cheap_alt_hint' => 'ادفع عبر :channel لتوفير الرسوم (امسح رمز QR عند إتمام الشراء).',
        'pay_with_channel' => 'المتابعة إلى الدفع',
        'channel_loading' => 'جارٍ إنشاء الدفع…',
        'channel_ready' => 'أكمل الدفع باستخدام التعليمات أدناه.',
        // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
        'channel_failed' => 'تعذر بدء إتمام الشراء عبر قناة الدفع',
        'confirm_paid' => 'لقد دفعت',
        'confirm_paid_help' => 'إذا أكملت الدفع خارج هذه الشاشة (مثلًا نقدًا أو عبر تحويل بنكي)، فانقر هنا لإعادة التحقق من حالة الفاتورة.',
        'change_channel' => 'تغيير قناة الدفع',
        'no_channels' => 'لا تتوفر طرق دفع لهذه الفاتورة.',
        'permission_required' => 'لا تملك صلاحية بدء الدفع. اطلب من مسؤول منحك صلاحية «بدء الدفع».',
        'synced' => 'تمت مزامنة حالة الدفع.',
        'syncing' => 'جارٍ إجراء المزامنة…',
        'sync_pending' => 'لا يزال الدفع معلقًا.',
        'sync_failed' => 'تعذرت مزامنة حالة الدفع.',
    ],

    'instructions' => [
        'title' => 'تعليمات الدفع',
        'expires_at' => 'ادفع قبل :time',
        'qr_string' => 'بيانات QR',
        'bank' => 'البنك',
        'va_number' => 'الحساب الافتراضي',
        'account_name' => 'اسم الحساب',
        'open_ewallet' => 'فتح تطبيق المحفظة الإلكترونية',
        'copy' => 'نسخ',
        'copied' => 'تم النسخ إلى الحافظة',
        'copy_failed' => 'تعذر النسخ',
        // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
        'pending_hint' => 'تُحدَّث الحالة تلقائيًا بعد أن يؤكد مزود الدفع عملية الدفع.',
    ],

    'channels' => [
        'title' => 'قنوات الدفع',
        'list_title' => 'قائمة قنوات الدفع',
        'updated' => 'تم تحديث قناة الدفع.',
        'route_updated' => 'تم تحديث مسار قناة الدفع.',
        // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
        'routes_title' => 'مسارات بوابات الدفع',
        // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
        'routes_lead' => 'يجرّب إتمام الشراء المسارات الأساسية أو الوحيدة أولًا، ثم المسارات الاحتياطية. وضمن الدور نفسه، يُجرَّب المسار ذو رقم الأولوية الأقل أولًا. تُتجاوز المسارات غير النشطة أو التي تفتقد بيانات اعتماد بوابة الدفع.',
        'empty' => 'لم يتم إعداد قنوات دفع. شغّل php artisan payments:sync-routing-policy بعد PaymentGatewaySeeder.',
        'no_routes' => 'لا توجد مسارات بوابة مرتبطة بهذه القناة.',
        // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
        'no_results' => 'لا توجد قناة تطابق بحثك.',
        'search_label' => 'البحث في القنوات',
        'search_placeholder' => 'ابحث بالاسم أو الرمز أو النوع…',
        // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
        'inactive_channel' => 'مخفية عن إتمام الشراء',
        'primary_route' => 'تُجرَّب أولًا',
        'priority_hint' => 'الرقم الأقل يعني أولوية أعلى ضمن دور المسار نفسه. لا يمكن تعديل الأولويات التي تديرها السياسة هنا.',
        'priority_autosave_hint' => 'تُحفظ تلقائيًا',
        'visible_count' => '{0} لا توجد قنوات|{1} قناة واحدة ظاهرة|[2,*] :count قنوات ظاهرة',
        'route_count' => '{0} لا توجد مسارات|{1} مسار واحد|[2,*] :count مسارات',
        'status' => [
            'active' => 'نشطة',
            'inactive' => 'غير نشطة',
        ],
        'kinds' => [
            'qris' => 'QRIS',
            'va' => 'حساب افتراضي',
            'ewallet' => 'محفظة إلكترونية',
        ],
        'intro' => [
            // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
            'title' => 'كيفية عمل توجيه إتمام الشراء عبر قنوات الدفع',
            // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
            'lead' => 'يختار العملاء قناة منطقية (QRIS أو الحساب البنكي الافتراضي أو المحفظة الإلكترونية). ثم يجرّب Monolith مسارات بوابة الدفع وفق ترتيب السياسة (دور المسار ثم الأولوية) حتى تُنشأ تعليمات الدفع.',
            'steps' => [
                [
                    'title' => 'تفعيل القناة',
                    // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
                    'body' => 'تُخفى القنوات غير النشطة عن إتمام الشراء. ويمكن أن تبقى المسارات مُهيّأة أثناء إيقاف القناة.',
                ],
                [
                    'title' => 'فهم أدوار المسارات',
                    'body' => 'تُجرَّب المسارات الأساسية أو الوحيدة قبل المسارات الاحتياطية. وتُطبق الأولوية (10 قبل 20) ضمن الدور نفسه. تأتي الرسوم والتسوية من Routing_Price.yaml.',
                ],
                [
                    // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
                    'title' => 'البديل عند إتمام الشراء',
                    // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
                    'body' => 'إذا فشلت كل المسارات الصالحة أو فُقدت بيانات الاعتماد، فقد يعرض إتمام الشراء صفحة Snap المستضافة أو يعيد التوجيه إلى iPaymu.',
                ],
            ],
        ],
        'stats' => [
            'channels_active' => 'القنوات النشطة',
            'routes_active' => 'المسارات النشطة',
            // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
            'checkout_hint' => 'سلوك إتمام الشراء',
            // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
            'checkout_body' => 'لا تظهر للدافعين إلا القنوات النشطة التي تملك مسارًا صالحًا واحدًا على الأقل.',
        ],
        'columns' => [
            'code' => 'الرمز',
            'display_name' => 'اسم العرض',
            'kind' => 'النوع',
            'sort_order' => 'ترتيب العرض',
            'is_active' => 'نشطة',
        ],
        'route_columns' => [
            // curated at 2026-09-22T20:24:45+07:00 — ar curation wave
            'gateway' => 'بوابة الدفع',
            'provider_type' => 'نوع المزود',
            'provider_channel' => 'قناة المزود',
            'priority' => 'الأولوية',
            'is_active' => 'نشطة',
            'route_role' => 'الدور',
            'settlement' => 'التسوية',
            'fee' => 'الرسوم',
            'policy_managed' => 'تديرها السياسة',
        ],
        'route_roles' => [
            'primary' => 'أساسي',
            'fallback' => 'احتياطي',
            'only' => 'وحيد',
        ],
        'sync_policy' => 'مزامنة سياسة التوجيه',
        'sync_policy_hint' => 'أعد تحميل القنوات والمسارات من Routing_Price.yaml.',
        'sync_policy_done' => 'تمت مزامنة سياسة التوجيه (:channels قناة و:routes مسارًا). جارٍ إعادة التحميل…',
    ],
];
