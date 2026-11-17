<?php

return [
    'success' => 'تم الدفع بنجاح.',
    'failed' => 'فشل الدفع. أعد المحاولة.',
    'pending' => 'تجري معالجة الدفع.',

    'gateways' => [
        'list' => 'تم جلب بوابات الدفع بنجاح.',
        'show' => 'تم جلب بوابة الدفع بنجاح.',
        'created' => 'تم إنشاء بوابة الدفع بنجاح.',
        'updated' => 'تم تحديث بوابة الدفع بنجاح.',
        'deleted' => 'تم حذف بوابة الدفع بنجاح.',
        'inactive' => 'بوابة الدفع المحددة غير نشطة.',
        'none_active' => 'لم يتم إعداد أي بوابة دفع نشطة.',
    ],

    'invoices' => [
        'list' => 'تم جلب الفواتير بنجاح.',
        'show' => 'تم جلب الفاتورة بنجاح.',
        // curated at 2026-09-22T20:32:25+07:00 — ar curation wave
        'checkout_channels' => 'تم تحميل قنوات الدفع لإتمام الشراء.',
        'checkout_channels_not_payable' => 'الفاتورة غير متاحة للدفع.',
    ],

    'income' => [
        'overview' => 'تم تحميل نظرة عامة على دخل المدفوعات.',
        'invoices' => 'تم تحميل فواتير دخل المدفوعات.',
    ],

    'subscriptions' => [
        'list' => 'تم جلب الاشتراكات بنجاح.',
        'show' => 'تم جلب الاشتراك بنجاح.',
        'created' => 'تم إنشاء الاشتراك بنجاح.',
        'updated' => 'تم تحديث الاشتراك بنجاح.',
        'deleted' => 'تم حذف الاشتراك بنجاح.',
    ],

    'midtrans' => [
        'snap_created' => 'تم إنشاء معاملة Snap.',
        'charge_created' => 'تم إنشاء عملية خصم عبر Core API.',
        'status_retrieved' => 'تم جلب حالة المعاملة.',
        'cancel_ok' => 'تم إلغاء المعاملة.',
        'expire_ok' => 'انتهت صلاحية المعاملة.',
        'refund_ok' => 'تمت معالجة الاسترداد.',
        'approve_ok' => 'تمت الموافقة على المعاملة.',
        'deny_ok' => 'رُفضت المعاملة.',
        'sync_ok' => 'تمت مزامنة حالة الفاتورة مع Midtrans.',
        'invoice_not_midtrans' => 'الفاتورة غير مرتبطة ببوابة Midtrans.',
        'invoice_not_payable' => 'يجب أن تكون الفاتورة معلقة أو في حالة تحدٍّ لبدء الدفع.',
        'external_sync_failed' => 'تعذر التحقق من حالة الدفع مع Midtrans قبل بدء الدفع.',
        'webhook_invalid_json' => 'نص JSON غير صالح.',
        'webhook_invalid_signature' => 'توقيع الإشعار غير صالح.',
        // curated at 2026-09-22T20:32:25+07:00 — ar curation wave
        'webhook_missing_order' => 'الحقل order_id مفقود في الإشعار.',
        'webhook_ack_no_status' => 'تم تأكيد استلام الإشعار (تعذر تحديد الحالة).',
        'webhook_orphan' => 'تم قبول الإشعار (لا توجد فاتورة مطابقة).',
        'webhook_ok' => 'تمت معالجة الإشعار.',
        'webhook_ping' => 'عنوان خطاف الويب Midtrans قابل للوصول (استخدم POST للإشعارات).',
        'webhook_subscription_ack' => 'تم تأكيد استلام إشعار الاشتراك.',
        'open_api_public_key_missing' => 'لم تتم تهيئة مفتاح Midtrans Open API العام.',
        'open_api_signature_invalid' => 'قيمة X-SIGNATURE لإشعار Open API غير صالحة أو تعذر التحقق منها.',
    ],

    'checkout' => [
        'active_session_pending' => 'لا تزال جلسة الدفع السابقة معلقة لدى المزود. انتظر انتهاءها أو اكتمالها قبل تبديل القناة.',
        'gateway_provider_mismatch' => 'بوابة دفع الفاتورة لا تطابق مزود الدفع المطلوب.',
    ],

    'channels' => [
        'list' => 'تم جلب قنوات الدفع بنجاح.',
        'show' => 'تم جلب قناة الدفع بنجاح.',
        'updated' => 'تم تحديث قناة الدفع بنجاح.',
        'checkout_created' => 'تم إنشاء تعليمات الدفع.',
        'checkout_failed' => 'فشلت جميع مسارات الدفع لهذه القناة.',
        'route_updated' => 'تم تحديث مسار قناة الدفع.',
        'not_found' => 'لم يتم العثور على قناة الدفع.',
        'not_available' => 'قناة الدفع المحددة غير متاحة.',
        'unsupported_gateway' => 'لم يتم إعداد بوابة دفع مدعومة لهذه القناة.',
        'policy_managed_route' => 'يُدار هذا المسار بواسطة سياسة التوجيه. لا يمكن تغيير سوى التفعيل.',
    ],

    'ipaymu' => [
        'snap_created' => 'تم إنشاء جلسة دفع مستضافة عبر iPaymu.',
        'charge_created' => 'تم إنشاء دفعة مباشرة عبر iPaymu.',
        'status_retrieved' => 'تم جلب حالة المعاملة من iPaymu.',
        'sync_ok' => 'تمت مزامنة حالة الفاتورة مع iPaymu.',
        'invoice_not_ipaymu' => 'الفاتورة غير مرتبطة ببوابة iPaymu.',
        'invoice_not_payable' => 'يجب أن تكون الفاتورة معلقة أو في حالة تحدٍّ لبدء الدفع.',
        'external_sync_failed' => 'تعذر التحقق من حالة الدفع مع iPaymu قبل بدء الدفع.',
        'active_payment_exists' => 'توجد جلسة دفع قيد التنفيذ لهذه الفاتورة بالفعل. أكملها أو ألغها قبل بدء عملية دفع جديدة.',
        'invoice_not_found' => 'لم يتم العثور على فاتورة iPaymu لرقم الطلب هذا.',
        'missing_transaction_id' => 'لا تحتوي الفاتورة على معرّف معاملة iPaymu بعد؛ أكمل جلسة دفع أولًا.',
        'buyer_required' => 'تعذر تحديد بيانات المشتري من الطلب أو الفاتورة أو ملف المستخدم المصادق عليه.',
        'webhook_invalid_json' => 'نص JSON غير صالح.',
        'webhook_invalid_signature' => 'توقيع الإشعار غير صالح.',
        // curated at 2026-09-22T20:32:25+07:00 — ar curation wave
        'webhook_invalid_va' => 'ترويسة VA لا تطابق قيمة iPaymu VA المهيأة.',
        'webhook_ack_no_status' => 'تم تأكيد استلام الإشعار (تعذر تحديد الحالة).',
        'webhook_orphan' => 'تم قبول الإشعار (لا توجد فاتورة مطابقة).',
        'webhook_ok' => 'تمت معالجة الإشعار.',
        'webhook_ping' => 'عنوان خطاف الويب iPaymu قابل للوصول (استخدم POST للإشعارات).',
    ],
];
