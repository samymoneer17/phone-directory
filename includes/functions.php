<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Helper Functions
 * International Phone Directory
 * ============================================================
 * Utility functions for country detection, formatting,
 * output sanitization, flash messages, and more.
 */

defined('APP_STARTED') || require_once __DIR__ . '/config.php';

// ============================================================
// Country Codes Database (50+ countries)
// ============================================================
defined('COUNTRY_CODES') or define('COUNTRY_CODES', [
    // Middle East & North Africa
    '+967' => ['name' => 'اليمن', 'name_en' => 'Yemen', 'flag' => '🇾🇪', 'code' => 'YE'],
    '+966' => ['name' => 'السعودية', 'name_en' => 'Saudi Arabia', 'flag' => '🇸🇦', 'code' => 'SA'],
    '+971' => ['name' => 'الإمارات', 'name_en' => 'United Arab Emirates', 'flag' => '🇦🇪', 'code' => 'AE'],
    '+968' => ['name' => 'عُمان', 'name_en' => 'Oman', 'flag' => '🇴🇲', 'code' => 'OM'],
    '+973' => ['name' => 'البحرين', 'name_en' => 'Bahrain', 'flag' => '🇧🇭', 'code' => 'BH'],
    '+974' => ['name' => 'قطر', 'name_en' => 'Qatar', 'flag' => '🇶🇦', 'code' => 'QA'],
    '+965' => ['name' => 'الكويت', 'name_en' => 'Kuwait', 'flag' => '🇰🇼', 'code' => 'KW'],
    '+961' => ['name' => 'لبنان', 'name_en' => 'Lebanon', 'flag' => '🇱🇧', 'code' => 'LB'],
    '+962' => ['name' => 'الأردن', 'name_en' => 'Jordan', 'flag' => '🇯🇴', 'code' => 'JO'],
    '+963' => ['name' => 'سوريا', 'name_en' => 'Syria', 'flag' => '🇸🇾', 'code' => 'SY'],
    '+964' => ['name' => 'العراق', 'name_en' => 'Iraq', 'flag' => '🇮🇶', 'code' => 'IQ'],
    '+970' => ['name' => 'فلسطين', 'name_en' => 'Palestine', 'flag' => '🇵🇸', 'code' => 'PS'],
    '+972' => ['name' => 'إسرائيل', 'name_en' => 'Israel', 'flag' => '🇮🇱', 'code' => 'IL'],
    '+20'  => ['name' => 'مصر', 'name_en' => 'Egypt', 'flag' => '🇪🇬', 'code' => 'EG'],
    '+212' => ['name' => 'المغرب', 'name_en' => 'Morocco', 'flag' => '🇲🇦', 'code' => 'MA'],
    '+216' => ['name' => 'تونس', 'name_en' => 'Tunisia', 'flag' => '🇹🇳', 'code' => 'TN'],
    '+213' => ['name' => 'الجزائر', 'name_en' => 'Algeria', 'flag' => '🇩🇿', 'code' => 'DZ'],
    '+218' => ['name' => 'ليبيا', 'name_en' => 'Libya', 'flag' => '🇱🇾', 'code' => 'LY'],
    '+249' => ['name' => 'السودان', 'name_en' => 'Sudan', 'flag' => '🇸🇩', 'code' => 'SD'],
    '+252' => ['name' => 'الصومال', 'name_en' => 'Somalia', 'flag' => '🇸🇴', 'code' => 'SO'],
    '+253' => ['name' => 'جيبوتي', 'name_en' => 'Djibouti', 'flag' => '🇩🇯', 'code' => 'DJ'],
    '+269' => ['name' => 'جزر القمر', 'name_en' => 'Comoros', 'flag' => '🇰🇲', 'code' => 'KM'],
    '+248' => ['name' => 'سيشل', 'name_en' => 'Seychelles', 'flag' => '🇸🇨', 'code' => 'SC'],
    '+232' => ['name' => 'سيراليون', 'name_en' => 'Sierra Leone', 'flag' => '🇸🇱', 'code' => 'SL'],
    '+233' => ['name' => 'غانا', 'name_en' => 'Ghana', 'flag' => '🇬🇭', 'code' => 'GH'],
    '+234' => ['name' => 'نيجيريا', 'name_en' => 'Nigeria', 'flag' => '🇳🇬', 'code' => 'NG'],
    '+231' => ['name' => 'ليبيريا', 'name_en' => 'Liberia', 'flag' => '🇱🇷', 'code' => 'LR'],
    '+255' => ['name' => 'تنزانيا', 'name_en' => 'Tanzania', 'flag' => '🇹🇿', 'code' => 'TZ'],
    '+254' => ['name' => 'كينيا', 'name_en' => 'Kenya', 'flag' => '🇰🇪', 'code' => 'KE'],
    '+256' => ['name' => 'أوغندا', 'name_en' => 'Uganda', 'flag' => '🇺🇬', 'code' => 'UG'],
    '+250' => ['name' => 'رواندا', 'name_en' => 'Rwanda', 'flag' => '🇷🇼', 'code' => 'RW'],
    '+257' => ['name' => 'بوروندي', 'name_en' => 'Burundi', 'flag' => '🇧🇮', 'code' => 'BI'],
    '+226' => ['name' => 'بوركينا فاسو', 'name_en' => 'Burkina Faso', 'flag' => '🇧🇫', 'code' => 'BF'],
    '+225' => ['name' => 'ساحل العاج', 'name_en' => "Côte d'Ivoire", 'flag' => '🇨🇮', 'code' => 'CI'],
    '+228' => ['name' => 'توغو', 'name_en' => 'Togo', 'flag' => '🇹🇬', 'code' => 'TG'],
    '+229' => ['name' => 'بنين', 'name_en' => 'Benin', 'flag' => '🇧🇯', 'code' => 'BJ'],
    '+221' => ['name' => 'السنغال', 'name_en' => 'Senegal', 'flag' => '🇸🇳', 'code' => 'SN'],
    '+222' => ['name' => 'موريتانيا', 'name_en' => 'Mauritania', 'flag' => '🇲🇷', 'code' => 'MR'],
    '+240' => ['name' => 'غينيا الاستوائية', 'name_en' => 'Equatorial Guinea', 'flag' => '🇬🇶', 'code' => 'GQ'],
    '+241' => ['name' => 'الغابون', 'name_en' => 'Gabon', 'flag' => '🇬🇦', 'code' => 'GA'],
    '+243' => ['name' => 'الكونغو (ج.د.)', 'name_en' => 'DR Congo', 'flag' => '🇨🇩', 'code' => 'CD'],
    '+242' => ['name' => 'الكونغو', 'name_en' => 'Republic of Congo', 'flag' => '🇨🇬', 'code' => 'CG'],
    '+245' => ['name' => 'غينيا بيساو', 'name_en' => 'Guinea-Bissau', 'flag' => '🇬🇼', 'code' => 'GW'],
    '+246' => ['name' => 'جزر القمر البريطانية', 'name_en' => 'British Indian Ocean', 'flag' => '🇮🇴', 'code' => 'IO'],
    '+237' => ['name' => 'الكاميرون', 'name_en' => 'Cameroon', 'flag' => '🇨🇲', 'code' => 'CM'],
    '+238' => ['name' => 'الرأس الأخضر', 'name_en' => 'Cape Verde', 'flag' => '🇨🇻', 'code' => 'CV'],
    '+236' => ['name' => 'أفريقيا الوسطى', 'name_en' => 'Central African Republic', 'flag' => '🇨🇫', 'code' => 'CF'],
    '+235' => ['name' => 'تشاد', 'name_en' => 'Chad', 'flag' => '🇹🇩', 'code' => 'TD'],
    '+230' => ['name' => 'موريشيوس', 'name_en' => 'Mauritius', 'flag' => '🇲🇺', 'code' => 'MU'],
    '+261' => ['name' => 'مدغشقر', 'name_en' => 'Madagascar', 'flag' => '🇲🇬', 'code' => 'MG'],
    '+260' => ['name' => 'زامبيا', 'name_en' => 'Zambia', 'flag' => '🇿🇲', 'code' => 'ZM'],
    '+263' => ['name' => 'زيمبابوي', 'name_en' => 'Zimbabwe', 'flag' => '🇿🇼', 'code' => 'ZW'],
    '+264' => ['name' => 'ناميبيا', 'name_en' => 'Namibia', 'flag' => '🇳🇦', 'code' => 'NA'],
    '+265' => ['name' => 'مالاوي', 'name_en' => 'Malawi', 'flag' => '🇲🇼', 'code' => 'MW'],
    '+266' => ['name' => 'ليسوتو', 'name_en' => 'Lesotho', 'flag' => '🇱🇸', 'code' => 'LS'],
    '+267' => ['name' => 'بوتسوانا', 'name_en' => 'Botswana', 'flag' => '🇧🇼', 'code' => 'BW'],
    '+268' => ['name' => 'إسواتيني', 'name_en' => 'Eswatini', 'flag' => '🇸🇿', 'code' => 'SZ'],
    '+27'  => ['name' => 'جنوب أفريقيا', 'name_en' => 'South Africa', 'flag' => '🇿🇦', 'code' => 'ZA'],
    '+290' => ['name' => 'سانت هيلينا', 'name_en' => 'Saint Helena', 'flag' => '🇸🇭', 'code' => 'SH'],
    '+297' => ['name' => 'أروبا', 'name_en' => 'Aruba', 'flag' => '🇦🇼', 'code' => 'AW'],
    '+298' => ['name' => 'جزر فارو', 'name_en' => 'Faroe Islands', 'flag' => '🇫🇴', 'code' => 'FO'],
    '+299' => ['name' => 'جرينلاند', 'name_en' => 'Greenland', 'flag' => '🇬🇱', 'code' => 'GL'],
    '+1'   => ['name' => 'الولايات المتحدة/كندا', 'name_en' => 'USA/Canada', 'flag' => '🇺🇸', 'code' => 'US'],
    '+44'  => ['name' => 'المملكة المتحدة', 'name_en' => 'United Kingdom', 'flag' => '🇬🇧', 'code' => 'GB'],
    '+33'  => ['name' => 'فرنسا', 'name_en' => 'France', 'flag' => '🇫🇷', 'code' => 'FR'],
    '+49'  => ['name' => 'ألمانيا', 'name_en' => 'Germany', 'flag' => '🇩🇪', 'code' => 'DE'],
    '+39'  => ['name' => 'إيطاليا', 'name_en' => 'Italy', 'flag' => '🇮🇹', 'code' => 'IT'],
    '+34'  => ['name' => 'إسبانيا', 'name_en' => 'Spain', 'flag' => '🇪🇸', 'code' => 'ES'],
    '+90'  => ['name' => 'تركيا', 'name_en' => 'Turkey', 'flag' => '🇹🇷', 'code' => 'TR'],
    '+91'  => ['name' => 'الهند', 'name_en' => 'India', 'flag' => '🇮🇳', 'code' => 'IN'],
    '+92'  => ['name' => 'باكستان', 'name_en' => 'Pakistan', 'flag' => '🇵🇰', 'code' => 'PK'],
    '+93'  => ['name' => 'أفغانستان', 'name_en' => 'Afghanistan', 'flag' => '🇦🇫', 'code' => 'AF'],
    '+94'  => ['name' => 'سريلانكا', 'name_en' => 'Sri Lanka', 'flag' => '🇱🇰', 'code' => 'LK'],
    '+95'  => ['name' => 'ميانمار', 'name_en' => 'Myanmar', 'flag' => '🇲🇲', 'code' => 'MM'],
    '+880' => ['name' => 'بنغلاديش', 'name_en' => 'Bangladesh', 'flag' => '🇧🇩', 'code' => 'BD'],
    '+60'  => ['name' => 'ماليزيا', 'name_en' => 'Malaysia', 'flag' => '🇲🇾', 'code' => 'MY'],
    '+62'  => ['name' => 'إندونيسيا', 'name_en' => 'Indonesia', 'flag' => '🇮🇩', 'code' => 'ID'],
    '+63'  => ['name' => 'الفلبين', 'name_en' => 'Philippines', 'flag' => '🇵🇭', 'code' => 'PH'],
    '+66'  => ['name' => 'تايلاند', 'name_en' => 'Thailand', 'flag' => '🇹🇭', 'code' => 'TH'],
    '+84'  => ['name' => 'فيتنام', 'name_en' => 'Vietnam', 'flag' => '🇻🇳', 'code' => 'VN'],
    '+86'  => ['name' => 'الصين', 'name_en' => 'China', 'flag' => '🇨🇳', 'code' => 'CN'],
    '+81'  => ['name' => 'اليابان', 'name_en' => 'Japan', 'flag' => '🇯🇵', 'code' => 'JP'],
    '+82'  => ['name' => 'كوريا الجنوبية', 'name_en' => 'South Korea', 'flag' => '🇰🇷', 'code' => 'KR'],
    '+7'   => ['name' => 'روسيا', 'name_en' => 'Russia', 'flag' => '🇷🇺', 'code' => 'RU'],
    '+380' => ['name' => 'أوكرانيا', 'name_en' => 'Ukraine', 'flag' => '🇺🇦', 'code' => 'UA'],
    '+48'  => ['name' => 'بولندا', 'name_en' => 'Poland', 'flag' => '🇵🇱', 'code' => 'PL'],
    '+40'  => ['name' => 'رومانيا', 'name_en' => 'Romania', 'flag' => '🇷🇴', 'code' => 'RO'],
    '+43'  => ['name' => 'النمسا', 'name_en' => 'Austria', 'flag' => '🇦🇹', 'code' => 'AT'],
    '+41'  => ['name' => 'سويسرا', 'name_en' => 'Switzerland', 'flag' => '🇨🇭', 'code' => 'CH'],
    '+31'  => ['name' => 'هولندا', 'name_en' => 'Netherlands', 'flag' => '🇳🇱', 'code' => 'NL'],
    '+46'  => ['name' => 'السويد', 'name_en' => 'Sweden', 'flag' => '🇸🇪', 'code' => 'SE'],
    '+47'  => ['name' => 'النرويج', 'name_en' => 'Norway', 'flag' => '🇳🇴', 'code' => 'NO'],
    '+358' => ['name' => 'فنلندا', 'name_en' => 'Finland', 'flag' => '🇫🇮', 'code' => 'FI'],
    '+45'  => ['name' => 'الدنمارك', 'name_en' => 'Denmark', 'flag' => '🇩🇰', 'code' => 'DK'],
    '+351' => ['name' => 'البرتغال', 'name_en' => 'Portugal', 'flag' => '🇵🇹', 'code' => 'PT'],
    '+30'  => ['name' => 'اليونان', 'name_en' => 'Greece', 'flag' => '🇬🇷', 'code' => 'GR'],
    '+355' => ['name' => 'ألبانيا', 'name_en' => 'Albania', 'flag' => '🇦🇱', 'code' => 'AL'],
    '+389' => ['name' => 'مقدونيا الشمالية', 'name_en' => 'North Macedonia', 'flag' => '🇲🇰', 'code' => 'MK'],
    '+383' => ['name' => 'كوسوفو', 'name_en' => 'Kosovo', 'flag' => '🇽🇰', 'code' => 'XK'],
    '+381' => ['name' => 'صربيا', 'name_en' => 'Serbia', 'flag' => '🇷🇸', 'code' => 'RS'],
    '+382' => ['name' => 'الجبل الأسود', 'name_en' => 'Montenegro', 'flag' => '🇲🇪', 'code' => 'ME'],
    '+387' => ['name' => 'البوسنة والهرسك', 'name_en' => 'Bosnia and Herzegovina', 'flag' => '🇧🇦', 'code' => 'BA'],
    '+386' => ['name' => 'سلوفينيا', 'name_en' => 'Slovenia', 'flag' => '🇸🇮', 'code' => 'SI'],
    '+36'  => ['name' => 'المجر', 'name_en' => 'Hungary', 'flag' => '🇭🇺', 'code' => 'HU'],
    '+420' => ['name' => 'التشيك', 'name_en' => 'Czech Republic', 'flag' => '🇨🇿', 'code' => 'CZ'],
    '+421' => ['name' => 'سلوفاكيا', 'name_en' => 'Slovakia', 'flag' => '🇸🇰', 'code' => 'SK'],
    '+353' => ['name' => 'أيرلندا', 'name_en' => 'Ireland', 'flag' => '🇮🇪', 'code' => 'IE'],
    '+354' => ['name' => 'آيسلندا', 'name_en' => 'Iceland', 'flag' => '🇮🇸', 'code' => 'IS'],
    '+375' => ['name' => 'بيلاروسيا', 'name_en' => 'Belarus', 'flag' => '🇧🇾', 'code' => 'BY'],
    '+370' => ['name' => 'ليتوانيا', 'name_en' => 'Lithuania', 'flag' => '🇱🇹', 'code' => 'LT'],
    '+371' => ['name' => 'لاتفيا', 'name_en' => 'Latvia', 'flag' => '🇱🇻', 'code' => 'LV'],
    '+372' => ['name' => 'إستونيا', 'name_en' => 'Estonia', 'flag' => '🇪🇪', 'code' => 'EE'],
    '+374' => ['name' => 'أرمينيا', 'name_en' => 'Armenia', 'flag' => '🇦🇲', 'code' => 'AM'],
    '+376' => ['name' => 'أندورا', 'name_en' => 'Andorra', 'flag' => '🇦🇩', 'code' => 'AD'],
    '+378' => ['name' => 'سان مارينو', 'name_en' => 'San Marino', 'flag' => '🇸🇲', 'code' => 'SM'],
    '+377' => ['name' => 'موناكو', 'name_en' => 'Monaco', 'flag' => '🇲🇨', 'code' => 'MC'],
    '+994' => ['name' => 'أذربيجان', 'name_en' => 'Azerbaijan', 'flag' => '🇦🇿', 'code' => 'AZ'],
    '+992' => ['name' => 'طاجيكستان', 'name_en' => 'Tajikistan', 'flag' => '🇹🇯', 'code' => 'TJ'],
    '+993' => ['name' => 'تركمانستان', 'name_en' => 'Turkmenistan', 'flag' => '🇹🇲', 'code' => 'TM'],
    '+996' => ['name' => 'قيرغيزستان', 'name_en' => 'Kyrgyzstan', 'flag' => '🇰🇬', 'code' => 'KG'],
    '+998' => ['name' => 'أوزبكستان', 'name_en' => 'Uzbekistan', 'flag' => '🇺🇿', 'code' => 'UZ'],
    '+371' => ['name' => 'لاتفيا', 'name_en' => 'Latvia', 'flag' => '🇱🇻', 'code' => 'LV'],
    '+373' => ['name' => 'مولدوفا', 'name_en' => 'Moldova', 'flag' => '🇲🇩', 'code' => 'MD'],
    '+385' => ['name' => 'كرواتيا', 'name_en' => 'Croatia', 'flag' => '🇭🇷', 'code' => 'HR'],
    '+389' => ['name' => 'مقدونيا', 'name_en' => 'Macedonia', 'flag' => '🇲🇰', 'code' => 'MK'],
    '+550' => ['name' => 'البرازيل', 'name_en' => 'Brazil', 'flag' => '🇧🇷', 'code' => 'BR'],
    '+54'  => ['name' => 'الأرجنتين', 'name_en' => 'Argentina', 'flag' => '🇦🇷', 'code' => 'AR'],
    '+56'  => ['name' => 'تشيلي', 'name_en' => 'Chile', 'flag' => '🇨🇱', 'code' => 'CL'],
    '+57'  => ['name' => 'كولومبيا', 'name_en' => 'Colombia', 'flag' => '🇨🇴', 'code' => 'CO'],
    '+52'  => ['name' => 'المكسيك', 'name_en' => 'Mexico', 'flag' => '🇲🇽', 'code' => 'MX'],
    '+51'  => ['name' => 'بيرو', 'name_en' => 'Peru', 'flag' => '🇵🇪', 'code' => 'PE'],
    '+58'  => ['name' => 'فنزويلا', 'name_en' => 'Venezuela', 'flag' => '🇻🇪', 'code' => 'VE'],
    '+591' => ['name' => 'بوليفيا', 'name_en' => 'Bolivia', 'flag' => '🇧🇴', 'code' => 'BO'],
    '+593' => ['name' => 'الإكوادور', 'name_en' => 'Ecuador', 'flag' => '🇪🇨', 'code' => 'EC'],
    '+595' => ['name' => 'باراغواي', 'name_en' => 'Paraguay', 'flag' => '🇵🇾', 'code' => 'PY'],
    '+598' => ['name' => 'أوروغواي', 'name_en' => 'Uruguay', 'flag' => '🇺🇾', 'code' => 'UY'],
    '+61'  => ['name' => 'أستراليا', 'name_en' => 'Australia', 'flag' => '🇦🇺', 'code' => 'AU'],
    '+64'  => ['name' => 'نيوزيلندا', 'name_en' => 'New Zealand', 'flag' => '🇳🇿', 'code' => 'NZ'],
    '+672' => ['name' => 'أنتاركتيكا', 'name_en' => 'Antarctica', 'flag' => '🇦🇶', 'code' => 'AQ'],
    '+852' => ['name' => 'هونغ كونغ', 'name_en' => 'Hong Kong', 'flag' => '🇭🇰', 'code' => 'HK'],
    '+853' => ['name' => 'ماكاو', 'name_en' => 'Macau', 'flag' => '🇲🇴', 'code' => 'MO'],
    '+886' => ['name' => 'تايوان', 'name_en' => 'Taiwan', 'flag' => '🇹🇼', 'code' => 'TW'],
    '+977' => ['name' => 'نيبال', 'name_en' => 'Nepal', 'flag' => '🇳🇵', 'code' => 'NP'],
    '+975' => ['name' => 'بوتان', 'name_en' => 'Bhutan', 'flag' => '🇧🇹', 'code' => 'BT'],
    '+856' => ['name' => 'لاوس', 'name_en' => 'Laos', 'flag' => '🇱🇦', 'code' => 'LA'],
    '+65'  => ['name' => 'سنغافورة', 'name_en' => 'Singapore', 'flag' => '🇸🇬', 'code' => 'SG'],
    '+673' => ['name' => 'بروناي', 'name_en' => 'Brunei', 'flag' => '🇧🇳', 'code' => 'BN'],
    '+674' => ['name' => 'ناورو', 'name_en' => 'Nauru', 'flag' => '🇳🇷', 'code' => 'NR'],
    '+675' => ['name' => 'بابوا غينيا الجديدة', 'name_en' => 'Papua New Guinea', 'flag' => '🇵🇬', 'code' => 'PG'],
    '+676' => ['name' => 'تونغا', 'name_en' => 'Tonga', 'flag' => '🇹🇴', 'code' => 'TO'],
    '+677' => ['name' => 'جزر سليمان', 'name_en' => 'Solomon Islands', 'flag' => '🇸🇧', 'code' => 'SB'],
    '+678' => ['name' => 'فانواتو', 'name_en' => 'Vanuatu', 'flag' => '🇻🇺', 'code' => 'VU'],
    '+679' => ['name' => 'فيجي', 'name_en' => 'Fiji', 'flag' => '🇫🇯', 'code' => 'FJ'],
    '+680' => ['name' => 'بالاو', 'name_en' => 'Palau', 'flag' => '🇵🇼', 'code' => 'PW'],
    '+682' => ['name' => 'جزر كوك', 'name_en' => 'Cook Islands', 'flag' => '🇨🇰', 'code' => 'CK'],
    '+685' => ['name' => 'ساموا', 'name_en' => 'Samoa', 'flag' => '🇼🇸', 'code' => 'WS'],
    '+686' => ['name' => 'كيريباتي', 'name_en' => 'Kiribati', 'flag' => '🇰🇮', 'code' => 'KI'],
    '+687' => ['name' => 'كاليدونيا الجديدة', 'name_en' => 'New Caledonia', 'flag' => '🇳🇨', 'code' => 'NC'],
    '+688' => ['name' => 'توفالو', 'name_en' => 'Tuvalu', 'flag' => '🇹🇻', 'code' => 'TV'],
    '+689' => ['name' => 'بولينيزيا الفرنسية', 'name_en' => 'French Polynesia', 'flag' => '🇵🇫', 'code' => 'PF'],
    '+691' => ['name' => 'ميكرونيزيا', 'name_en' => 'Micronesia', 'flag' => '🇫🇲', 'code' => 'FM'],
    '+692' => ['name' => 'جزر مارشال', 'name_en' => 'Marshall Islands', 'flag' => '🇲🇭', 'code' => 'MH'],
    '+960' => ['name' => 'المالديف', 'name_en' => 'Maldives', 'flag' => '🇲🇻', 'code' => 'MV'],
    '+964' => ['name' => 'العراق', 'name_en' => 'Iraq', 'flag' => '🇮🇶', 'code' => 'IQ'],
    '+968' => ['name' => 'عُمان', 'name_en' => 'Oman', 'flag' => '🇴🇲', 'code' => 'OM'],
]);

// ============================================================
// Country Detection & Phone Functions
// ============================================================

/**
 * Detect country from a phone number
 * Extracts country code and returns country info
 *
 * @param string $phoneNumber Phone number in any format
 * @return array{countryCode: string, countryName: string, countryNameEn: string, flag: string, isoCode: string, nationalNumber: string}
 */
function detectCountry(string $phoneNumber): array
{
    // Default response
    $default = [
        'countryCode'    => '',
        'countryName'    => 'غير معروف',
        'countryNameEn'  => 'Unknown',
        'flag'           => '🌍',
        'isoCode'        => 'XX',
        'nationalNumber' => '',
    ];

    if (empty($phoneNumber)) {
        return $default;
    }

    // Normalize: remove all non-digit characters except leading +
    $clean = preg_replace('/[^\d+]/', '', $phoneNumber);

    // Handle different formats
    $digitsOnly = preg_replace('/[^0-9]/', '', $clean);
    $hasPlus = strpos($clean, '+') === 0;
    $hasDoubleZero = strpos($clean, '00') === 0;

    // Sort country codes by length descending (match longest first)
    $sortedCodes = array_keys(COUNTRY_CODES);
    usort($sortedCodes, fn($a, $b) => strlen($b) - strlen($a));

    $matchedCode = '';
    $nationalNumber = '';

    foreach ($sortedCodes as $code) {
        $codeDigits = ltrim($code, '+');

        // Try matching with different prefixes
        if ($hasPlus) {
            // +967123456789 format
            if (strpos($clean, $code) === 0) {
                $matchedCode = $code;
                $nationalNumber = substr($digitsOnly, strlen($codeDigits));
                break;
            }
        } elseif ($hasDoubleZero) {
            // 00967123456789 format
            if (strpos($clean, '00' . $codeDigits) === 0) {
                $matchedCode = $code;
                $nationalNumber = substr($digitsOnly, strlen('00' . $codeDigits));
                break;
            }
        } else {
            // 967123456789 format (no prefix)
            if (strpos($digitsOnly, $codeDigits) === 0) {
                $matchedCode = $code;
                $nationalNumber = substr($digitsOnly, strlen($codeDigits));
                break;
            }
        }
    }

    if ($matchedCode === '') {
        $default['nationalNumber'] = $digitsOnly;
        return $default;
    }

    $country = COUNTRY_CODES[$matchedCode];

    return [
        'countryCode'    => $matchedCode,
        'countryName'    => $country['name'],
        'countryNameEn'  => $country['name_en'],
        'flag'           => $country['flag'],
        'isoCode'        => $country['code'],
        'nationalNumber' => $nationalNumber,
    ];
}

/**
 * Format a phone number according to country conventions
 *
 * @param string $phoneNumber  Raw phone number
 * @param string $countryCode  Country dialing code (e.g., '+967')
 * @return string Formatted phone number
 */
function formatPhone(string $phoneNumber, string $countryCode = ''): string
{
    $country = detectCountry($phoneNumber);

    if ($country['countryCode'] !== '') {
        $countryCode = $country['countryCode'];
        $nationalNumber = $country['nationalNumber'];
    } else {
        $nationalNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
    }

    // Format based on country code
    $codeDigits = ltrim($countryCode, '+');

    // Common formatting patterns
    $formats = [
        'YE' => fn($n) => strlen($n) === 9 ? substr($n, 0, 3) . ' ' . substr($n, 3, 3) . ' ' . substr($n, 6) : $n,
        'SA' => fn($n) => strlen($n) === 9 ? '0' . substr($n, 0, 1) . ' ' . substr($n, 1, 4) . ' ' . substr($n, 5) : $n,
        'AE' => fn($n) => strlen($n) === 9 ? '0' . substr($n, 0, 1) . ' ' . substr($n, 1, 3) . ' ' . substr($n, 4) : $n,
        'US' => fn($n) => strlen($n) === 10 ? '(' . substr($n, 0, 3) . ') ' . substr($n, 3, 3) . '-' . substr($n, 6) : $n,
        'GB' => fn($n) => strlen($n) >= 10 ? '0' . substr($n, 0, 2) . ' ' . substr($n, 2, 4) . ' ' . substr($n, 6) : $n,
        'DE' => fn($n) => strlen($n) >= 10 ? '0' . substr($n, 0, 3) . ' ' . substr($n, 3) : $n,
        'FR' => fn($n) => strlen($n) === 9 ? '0' . implode(' ', str_split($n, 2)) : $n,
        'EG' => fn($n) => strlen($n) === 10 ? '0' . substr($n, 0, 1) . ' ' . substr($n, 1, 4) . ' ' . substr($n, 5) : $n,
        'JP' => fn($n) => strlen($n) >= 9 ? '0' . substr($n, 0, 1) . '-' . substr($n, 1, 4) . '-' . substr($n, 5) : $n,
        'CN' => fn($n) => strlen($n) === 11 ? '1' . ' ' . substr($n, 1, 3) . ' ' . substr($n, 4, 4) . ' ' . substr($n, 8) : $n,
    ];

    $isoCode = $country['isoCode'] ?? '';
    $formatter = $formats[$isoCode] ?? null;

    if ($formatter !== null) {
        $formatted = $formatter($nationalNumber);
    } else {
        // Generic formatting: group into chunks of 3-4 digits
        if (strlen($nationalNumber) > 6) {
            $formatted = substr($nationalNumber, 0, 3) . ' ' . substr($nationalNumber, 3);
        } else {
            $formatted = $nationalNumber;
        }
    }

    return $country['flag'] . ' ' . $countryCode . ' ' . $formatted;
}

/**
 * Get a human-readable time ago string
 *
 * @param string $datetime DateTime string
 * @return string e.g., "منذ 5 دقائق"
 */
function timeAgo(string $datetime): string
{
    if (empty($datetime)) {
        return '';
    }

    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '';
    }

    $diff = time() - $timestamp;
    $absDiff = abs($diff);

    if ($absDiff < 10) {
        return 'الآن';
    } elseif ($absDiff < 60) {
        $seconds = (int) $absDiff;
        return 'منذ ' . $seconds . ' ثانية';
    } elseif ($absDiff < 3600) {
        $minutes = (int) floor($absDiff / 60);
        return 'منذ ' . $minutes . ' ' . ($minutes === 1 ? 'دقيقة' : 'دقائق');
    } elseif ($absDiff < 86400) {
        $hours = (int) floor($absDiff / 3600);
        return 'منذ ' . $hours . ' ' . ($hours === 1 ? 'ساعة' : 'ساعات');
    } elseif ($absDiff < 2592000) {
        $days = (int) floor($absDiff / 86400);
        return 'منذ ' . $days . ' ' . ($days === 1 ? 'يوم' : 'أيام');
    } elseif ($absDiff < 31536000) {
        $months = (int) floor($absDiff / 2592000);
        return 'منذ ' . $months . ' ' . ($months === 1 ? 'شهر' : 'أشهر');
    } else {
        $years = (int) floor($absDiff / 31536000);
        return 'منذ ' . $years . ' ' . ($years === 1 ? 'سنة' : 'سنوات');
    }
}

/**
 * Format a number with thousands separator (Arabic)
 *
 * @param int|float $number
 * @return string
 */
function formatNumber($number): string
{
    return number_format((float) $number, 0, '.', ',');
}

/**
 * Format currency amount
 *
 * @param float  $amount
 * @param string $currency
 * @return string
 */
function formatCurrency(float $amount, string $currency = 'YER'): string
{
    $symbols = [
        'YER' => 'ر.ي',
        'SAR' => 'ر.س',
        'AED' => 'د.إ',
        'USD' => '$',
        'EUR' => '€',
    ];

    $symbol = $symbols[$currency] ?? $currency;
    $formatted = number_format($amount, 0, '.', ',');

    return $formatted . ' ' . $symbol;
}

/**
 * Sanitize output for HTML display
 *
 * @param string $str
 * @return string
 */
function sanitizeOutput(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Send a JSON response with proper headers
 *
 * @param mixed $data      Data to encode
 * @param int   $statusCode HTTP status code (default: 200)
 * @return void
 */
function jsonResponse($data, int $statusCode = 200): void
{
    // Clean any output buffer to ensure pure JSON response
    // This prevents PHP warnings/notices from corrupting the JSON
    while (ob_get_level()) {
        ob_end_clean();
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Redirect to a URL
 *
 * @param string $url
 * @return void
 */
function redirect(string $url): void
{
    // Prevent header injection
    $url = filter_var($url, FILTER_SANITIZE_URL);

    if (headers_sent()) {
        echo '<script>window.location.href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '";</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    } else {
        header('Location: ' . $url);
    }
    exit;
}

/**
 * Set a flash message in the session
 *
 * @param string $key     Message key/type (e.g., 'success', 'error', 'warning', 'info')
 * @param string $message Message content
 * @return void
 */
function flash(string $key, string $message): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }

    $_SESSION['flash'][$key] = $message;
}

/**
 * Get and clear a flash message
 *
 * @param string $key Message key/type
 * @return string|null Message content or null if not set
 */
function getFlash(string $key): ?string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['flash'][$key])) {
        return null;
    }

    $message = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);

    return $message;
}

/**
 * Get all flash messages and clear them
 *
 * @return array
 */
function getAllFlash(): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $messages = $_SESSION['flash'] ?? [];
    $_SESSION['flash'] = [];

    return $messages;
}

/**
 * Get a page URL with the current base URL
 *
 * @param string $page Page name (e.g., 'login.php')
 * @return string Full URL
 */
function getPageUrl(string $page): string
{
    return rtrim(SITE_URL, '/') . '/' . ltrim($page, '/');
}

/**
 * Truncate text to a maximum length with ellipsis
 *
 * @param string $text    Input text
 * @param int    $length  Maximum length
 * @param string $suffix  Suffix to append (default: '...')
 * @return string
 */
function truncateText(string $text, int $length = 100, string $suffix = '...'): string
{
    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }

    $truncated = mb_substr($text, 0, $length, 'UTF-8');

    // Don't cut words in half
    $lastSpace = mb_strrpos($truncated, ' ', 0, 'UTF-8');
    if ($lastSpace !== false && $lastSpace > $length * 0.7) {
        $truncated = mb_substr($truncated, 0, $lastSpace, 'UTF-8');
    }

    return $truncated . $suffix;
}

/**
 * Generate an SVG avatar with initials from a name
 *
 * @param string $name   Person's name
 * @param int    $size   Avatar size in pixels (default: 80)
 * @param string $bgColor Background color (default: random)
 * @return string SVG HTML
 */
function generateAvatar(string $name, int $size = 80, ?string $bgColor = null): string
{
    if (empty($name)) {
        $name = '?';
    }

    // Extract initials (first letter of each word, max 2)
    $words = preg_split('/\s+/', trim($name));
    $initials = '';
    if (count($words) >= 2) {
        $initials = mb_substr($words[0], 0, 1, 'UTF-8') . mb_substr($words[1], 0, 1, 'UTF-8');
    } else {
        $initials = mb_substr($name, 0, min(2, mb_strlen($name, 'UTF-8')), 'UTF-8');
    }

    $initials = mb_strtoupper($initials, 'UTF-8');

    // Generate consistent color from name
    if ($bgColor === null) {
        $hash = 0;
        for ($i = 0; $i < mb_strlen($name, 'UTF-8'); $i++) {
            $hash = ord(mb_substr($name, $i, 1, 'UTF-8')) + (($hash << 5) - $hash);
        }

        $colors = [
            '#e74c3c', '#e67e22', '#f1c40f', '#2ecc71', '#1abc9c',
            '#3498db', '#9b59b6', '#e91e63', '#00bcd4', '#ff5722',
            '#795548', '#607d8b', '#8bc34a', '#ff9800', '#673ab7',
        ];

        $bgColor = $colors[abs($hash) % count($colors)];
    }

    $fontSize = (int) ($size * 0.38);
    $textY = (int) ($size * 0.62);

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '">';
    $svg .= '<rect width="' . $size . '" height="' . $size . '" rx="50%" fill="' . $bgColor . '"/>';
    $svg .= '<text x="50%" y="' . $textY . '" font-family="Cairo, Arial, sans-serif" font-size="' . $fontSize . '" font-weight="bold" fill="white" text-anchor="middle">' . htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') . '</text>';
    $svg .= '</svg>';

    // Convert to data URI for use in img src
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

/**
 * Validate a subscription plan name
 *
 * @param string $plan Plan name
 * @return bool
 */
function validatePlan(string $plan): bool
{
    return isset(PLANS[strtoupper($plan)]);
}

/**
 * Get the current page URL
 *
 * @return string
 */
function currentUrl(): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    return $protocol . '://' . $host . $uri;
}

/**
 * Check if the current request is an AJAX request
 *
 * @return bool
 */
function isAjax(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get a value from $_GET with a default
 *
 * @param string $key     Query parameter name
 * @param mixed  $default Default value
 * @return mixed
 */
function get(string $key, $default = null)
{
    return $_GET[$key] ?? $default;
}

/**
 * Get a value from $_POST with a default
 *
 * @param string $key     POST field name
 * @param mixed  $default Default value
 * @return mixed
 */
function post(string $key, $default = null)
{
    return $_POST[$key] ?? $default;
}

/**
 * Check if a string starts with a given substring
 *
 * @param string $haystack
 * @param string $needle
 * @return bool
 */
function startsWith(string $haystack, string $needle): bool
{
    return strncmp($haystack, $needle, strlen($needle)) === 0;
}

/**
 * Check if a string ends with a given substring
 *
 * @param string $haystack
 * @param string $needle
 * @return bool
 */
function endsWith(string $haystack, string $needle): bool
{
    $length = strlen($needle);
    if ($length === 0) {
        return true;
    }
    return substr($haystack, -$length) === $needle;
}

/**
 * Get gravatar URL for an email
 *
 * @param string $email User email
 * @param int    $size  Image size
 * @return string
 */
function getGravatar(string $email, int $size = 80): string
{
    $hash = md5(strtolower(trim($email)));
    return 'https://www.gravatar.com/avatar/' . $hash . '?s=' . $size . '&d=mp';
}

/**
 * Pretty print a variable (for debugging)
 *
 * @param mixed $var
 * @param bool  $die  Whether to die after printing
 * @return void
 */
function dd($var, bool $die = true): void
{
    echo '<pre style="direction: ltr; text-align: left; background: #1e1e1e; color: #d4d4d4; padding: 16px; border-radius: 8px; margin: 16px; font-family: monospace; font-size: 13px; white-space: pre-wrap; word-wrap: break-word; max-height: 500px; overflow: auto;">';
    print_r($var);
    echo '</pre>';

    if ($die) {
        exit;
    }
}

/**
 * Generate a unique filename
 *
 * @param string $originalName Original file name
 * @param string $directory    Target directory
 * @return string
 */
function generateUniqueFilename(string $originalName, string $directory = ''): string
{
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $basename = bin2hex(random_bytes(8));

    if ($directory !== '') {
        // Ensure uniqueness in directory
        $counter = 0;
        $filename = $basename . ($extension ? '.' . $extension : '');
        while (file_exists($directory . '/' . $filename)) {
            $counter++;
            $filename = $basename . '_' . $counter . ($extension ? '.' . $extension : '');
        }
        return $filename;
    }

    return $basename . ($extension ? '.' . $extension : '');
}

/**
 * Get a list of all countries for select dropdowns
 *
 * @return array Array of [code => name]
 */
function getCountriesList(): array
{
    $list = [];
    foreach (COUNTRY_CODES as $code => $info) {
        $list[$code] = $info['flag'] . ' ' . $info['name'] . ' (' . $code . ')';
    }
    asort($list);
    return $list;
}

/**
 * Build a query string from an associative array
 *
 * @param array  $params     Parameters
 * @param string $separator  Separator (default: '&')
 * @return string
 */
function buildQueryString(array $params, string $separator = '&'): string
{
    return http_build_query($params, '', $separator);
}
