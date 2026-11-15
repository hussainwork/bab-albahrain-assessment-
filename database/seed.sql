USE gatee_shop;

INSERT INTO products (name, name_ar, description, description_ar, price, currency, image)
SELECT * FROM (
    SELECT
        'Website Starter Package' AS name,
        'باقة إطلاق الموقع' AS name_ar,
        'Responsive 5-page business website with contact form and basic SEO.' AS description,
        'موقع تعريفي متجاوب من 5 صفحات مع نموذج تواصل وتهيئة أساسية لمحركات البحث.' AS description_ar,
        350.000 AS price,
        'BHD' AS currency,
        'bi-window-stack' AS image
    UNION ALL
    SELECT
        'Mobile App Maintenance - 1 Month',
        'صيانة تطبيق الجوال - شهر واحد',
        'Bug fixes, OS compatibility updates and store release support for one month.',
        'إصلاح الأخطاء وتحديثات التوافق مع أنظمة التشغيل ودعم النشر على المتاجر لمدة شهر.',
        120.000,
        'BHD',
        'bi-phone'
    UNION ALL
    SELECT
        'Domain + Hosting - 1 Year',
        'نطاق واستضافة - سنة واحدة',
        '.com domain registration with managed hosting, SSL and daily backups.',
        'تسجيل نطاق .com مع استضافة مُدارة وشهادة SSL ونسخ احتياطي يومي.',
        48.000,
        'BHD',
        'bi-hdd-network'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM products);
