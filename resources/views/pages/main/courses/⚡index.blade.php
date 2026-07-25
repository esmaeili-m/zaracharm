
text/x-generic checkout.blade.php ( HTML document, UTF-8 Unicode text, with very long lines )

@extends('home.master')

@section('title' , 'تسویه حساب')
@section('content')
    <main class="allCheckoutIndex width">
        @if (count($getCart[4]))
            <div class="alert">
                محصولات
                <br>
                @foreach($getCart[4] as $val)
                    {{$val}}
                    <br>
                @endforeach
                از سبد شما حذف شدند.
            </div>
        @endif
        @if (\Session::has('message'))
            <div class="alert">
                {!! \Session::get('message') !!}
            </div>
        @endif
        @if (\Session::has('success'))
            <div class="success">
                {!! \Session::get('success') !!}
            </div>
        @endif
        <div class="address" aria-label="Breadcrumb">
            <a href="/">خانه</a>
            <i>
                <svg class="icon">
                    <use xlink:href="#left"></use>
                </svg>
            </i>
            <a href="{{url()->current()}}">تسویه حساب</a>
        </div>
        <h1>تسویه حساب</h1>
        <div class="discount1">
            <p>کد تخفیف دارید؟ جهت ثبت کد اینجا کلیک کنید.</p>
            <div class="codes">
                <div class="name">اگر کد تخفیف دارید لطفا پایین اعمال کنید</div>
                <div class="box">
                    <label>
                        <input type="text" name="discount" placeholder="کد تخفیف">
                        <button>اعمال کد تخفیف</button>
                    </label>
                </div>
            </div>
        </div>
        <div class="container1">
            <div class="right">
                <form action="/add-address" method="POST" class="CreateAddress">
                    <div class="title2"><span>آدرس محل ارسال</span></div>
                    <div class="form">
                        <div class="addressField">
                            @csrf
                            <input type="hidden" name="city1" value="{{$address?$address->city:'تهران'}}">
                            <input type="hidden" name="state1" value="{{$address?$address->state:'تهران'}}">
                            <input type="hidden" name="lng" value="">
                            <input type="hidden" name="lat" value="">
                            <div class="map1">
                                <div id="map1"></div>
                            </div>
                            <div class="items">
                                <div class="item">
                                    <label for="state">استان <span>*</span></label>
                                    <select id="state" name="state">
                                        <option value="تهران">تهران</option>
                                        <option value="خراسان رضوی">خراسان رضوی</option>
                                        <option value="اصفهان">اصفهان</option>
                                        <option value="فارس">فارس</option>
                                        <option value="خوزستان">خوزستان</option>
                                        <option value="آذربایجان شرقی">آذربایجان شرقی</option>
                                        <option value="مازندران">مازندران</option>
                                        <option value="آذربایجان غربی">آذربایجان غربی</option>
                                        <option value="کرمان">کرمان</option>
                                        <option value="سیستان و بلوچستان">سیستان و بلوچستان</option>
                                        <option value="البرز">البرز</option>
                                        <option value="گیلان">گیلان</option>
                                        <option value="کرمانشاه">کرمانشاه</option>
                                        <option value="گلستان">گلستان</option>
                                        <option value="هرمزگان">هرمزگان</option>
                                        <option value="لرستان">لرستان</option>
                                        <option value="همدان">همدان</option>
                                        <option value="کردستان">کردستان</option>
                                        <option value="مرکزی">مرکزی</option>
                                        <option value="قم">قم</option>
                                        <option value="قزوین">قزوین</option>
                                        <option value="اردبیل">اردبیل</option>
                                        <option value="بوشهر">بوشهر</option>
                                        <option value="یزد">یزد</option>
                                        <option value="زنجان">زنجان</option>
                                        <option value="چهارمحال و بختیاری">چهارمحال و بختیاری</option>
                                        <option value="خراسان شمالی">خراسان شمالی</option>
                                        <option value="خراسان جنوبی">خراسان جنوبی</option>
                                        <option value="کهگیلویه و بویراحمد">کهگیلویه و بویراحمد</option>
                                        <option value="سمنان">سمنان</option>
                                        <option value="ایلام">ایلام</option>
                                    </select>
                                    <div id="validation-state"></div>
                                </div>
                                <div class="item">
                                    <label class="title1">شهر <span>*</span></label>
                                    <select name="city" id="city" class="city1">
                                        <option value="تهران">تهران</option>
                                        <option value="شاهدشهر">شاهدشهر</option>
                                        <option value="پیشوا">پیشوا</option>
                                        <option value="جوادآباد">جوادآباد</option>
                                        <option value="ارجمند">ارجمند</option>
                                        <option value="ری">ری</option>
                                        <option value="نصیرشهر">نصیرشهر</option>
                                        <option value="رودهن">رودهن</option>
                                        <option value="اندیشه">اندیشه</option>
                                        <option value="نسیم شهر">نسیم شهر</option>
                                        <option value="صباشهر">صباشهر</option>
                                        <option value="ملارد">ملارد</option>
                                        <option value="شمشک">شمشک</option>
                                        <option value="پاکدشت">پاکدشت</option>
                                        <option value="باقرشهر">باقرشهر</option>
                                        <option value="احمد آباد مستوفی">احمد آباد مستوفی</option>
                                        <option value="کیلان">کیلان</option>
                                        <option value="قرچک">قرچک</option>
                                        <option value="فردوسیه">فردوسیه</option>
                                        <option value="گلستان">گلستان</option>
                                        <option value="ورامین">ورامین</option>
                                        <option value="فیروزکوه">فیروزکوه</option>
                                        <option value="فشم">فشم</option>
                                        <option value="پرند">پرند</option>
                                        <option value="آبعلی">آبعلی</option>
                                        <option value="چهاردانگه">چهاردانگه</option>
                                        <option value="بومهن">بومهن</option>
                                        <option value="وحیدیه">وحیدیه</option>
                                        <option value="صفادشت">صفادشت</option>
                                        <option value="لواسان">لواسان</option>
                                        <option value="فرون اباد">فرون اباد</option>
                                        <option value="کهریزک">کهریزک</option>
                                        <option value="رباطکریم">رباطکریم</option>
                                        <option value="آبسرد">آبسرد</option>
                                        <option value="باغستان">باغستان</option>
                                        <option value="صالحیه">صالحیه</option>
                                        <option value="شهریار">شهریار</option>
                                        <option value="قدس">قدس</option>
                                        <option value="تجریش">تجریش</option>
                                        <option value="	شریف آباد">	شریف آباد</option>
                                        <option value="حسن آباد">حسن آباد</option>
                                        <option value="اسلامشهر">اسلامشهر</option>
                                        <option value="دماوند">دماوند</option>
                                        <option value="پردیس">پردیس</option>
                                    </select>
                                    <select name="city" id="city" class="city2">
                                        <option value="نیل شهر">نیل شهر</option>
                                        <option value="بار">بار</option>
                                        <option value="جنگل">جنگل</option>
                                        <option value="درود">درود</option>
                                        <option value="رباط سنگ">رباط سنگ</option>
                                        <option value="سلطان آباد">سلطان آباد</option>
                                        <option value="فریمان">فریمان</option>
                                        <option value="گناباد">گناباد</option>
                                        <option value="کاریز">کاریز</option>
                                        <option value="همت آباد">همت آباد</option>
                                        <option value="سلامی">سلامی</option>
                                        <option value="باجگیران">باجگیران</option>
                                        <option value="بجستان">بجستان</option>
                                        <option value="چناران">چناران</option>
                                        <option value="درگز">درگز</option>
                                        <option value="کلات">کلات</option>
                                        <option value="چکنه">چکنه</option>
                                        <option value="نصرآباد">نصرآباد</option>
                                        <option value="بردسکن">بردسکن</option>
                                        <option value="مشهد">مشهد</option>
                                        <option value="کدکن">کدکن</option>
                                        <option value="نقاب">نقاب</option>
                                        <option value="قلندرآباد">قلندرآباد</option>
                                        <option value="کاشمر">کاشمر</option>
                                        <option value="شاندیز">شاندیز</option>
                                        <option value="نشتیفان">نشتیفان</option>
                                        <option value="ششتمد">ششتمد</option>
                                        <option value="شادمهر">شادمهر</option>
                                        <option value="عشق آباد">عشق آباد</option>
                                        <option value="چاپشلو">چاپشلو</option>
                                        <option value="رشتخوار">رشتخوار</option>
                                        <option value="قدمگاه">قدمگاه</option>
                                        <option value="صالح آباد">صالح آباد</option>
                                        <option value="داورزن">داورزن</option>
                                        <option value="فرهادگرد">فرهادگرد</option>
                                        <option value="کاخک">کاخک</option>
                                        <option value="مشهدریزه">مشهدریزه</option>
                                        <option value="جغتای">جغتای</option>
                                        <option value="مزدآوند">مزدآوند</option>
                                        <option value="قوچان">قوچان</option>
                                        <option value="یونسی">یونسی</option>
                                        <option value="سنگان">سنگان</option>
                                        <option value="نوخندان">نوخندان</option>
                                        <option value="کندر">کندر</option>
                                        <option value="نیشابور">نیشابور</option>
                                        <option value="احمدابادصولت">احمدابادصولت</option>
                                        <option value="شهراباد">شهراباد</option>
                                        <option value="رضویه">رضویه</option>
                                        <option value="تربت حیدریه">تربت حیدریه</option>
                                        <option value="باخرز">باخرز</option>
                                        <option value="سفیدسنگ">سفیدسنگ</option>
                                        <option value="بیدخت">بیدخت</option>
                                        <option value="تایباد">تایباد</option>
                                        <option value="فیروزه">فیروزه</option>
                                        <option value="قاسم آباد">قاسم آباد</option>
                                        <option value="سبزوار">سبزوار</option>
                                        <option value="فیض آباد">فیض آباد</option>
                                        <option value="گلمکان">گلمکان</option>
                                        <option value="لطف آباد">لطف آباد</option>
                                        <option value="شهرزو">شهرزو</option>
                                        <option value="خرو">خرو</option>
                                        <option value="تربت جام">تربت جام</option>
                                        <option value="انابد">انابد</option>
                                        <option value="ملک آباد">ملک آباد</option>
                                        <option value="بایک">بایک</option>
                                        <option value="دولت آباد">دولت آباد</option>
                                        <option value="سرخس">سرخس</option>
                                        <option value="طرقبه">طرقبه</option>
                                        <option value="ریوش">ریوش</option>
                                        <option value="خواف">خواف</option>
                                        <option value="روداب">روداب</option>
                                        <option value="خلیل آباد">خلیل آباد</option>
                                    </select>
                                    <select name="city" id="city" class="city3">
                                        <option value="گزبرخوار">گزبرخوار</option>
                                        <option value="زیار">زیار</option>
                                        <option value="زرین شهر">زرین شهر</option>
                                        <option value="گلشن">گلشن</option>
                                        <option value="پیربکران">پیربکران</option>
                                        <option value="خالدآباد">خالدآباد</option>
                                        <option value="سجزی">سجزی</option>
                                        <option value="گوگد">گوگد</option>
                                        <option value="تیران">تیران</option>
                                        <option value="ونک">ونک</option>
                                        <option value="دهق">دهق</option>
                                        <option value="زواره">زواره</option>
                                        <option value="ابوزیدآباد">ابوزیدآباد</option>
                                        <option value="کاشان">کاشان</option>
                                        <option value="اصغرآباد">اصغرآباد</option>
                                        <option value="بافران">بافران</option>
                                        <option value="شهرضا">شهرضا</option>
                                        <option value="خور">خور</option>
                                        <option value="مجلسی">مجلسی</option>
                                        <option value="هرند">هرند</option>
                                        <option value="فولادشهر">فولادشهر</option>
                                        <option value="کمشچه">کمشچه</option>
                                        <option value="کلیشادوسودرجان">کلیشادوسودرجان</option>
                                        <option value="لای بید">لای بید</option>
                                        <option value="قهجاورستان">قهجاورستان</option>
                                        <option value="چرمهین">چرمهین</option>
                                        <option value="رزوه">رزوه</option>
                                        <option value="فریدونشهر">فریدونشهر</option>
                                        <option value="طرق رود">طرق رود</option>
                                        <option value="نصرآباد">نصرآباد</option>
                                        <option value="برزک">برزک</option>
                                        <option value="سفیدشهر">سفیدشهر</option>
                                        <option value="سمیرم">سمیرم</option>
                                        <option value="گلدشت">گلدشت</option>
                                        <option value="اردستان">اردستان</option>
                                        <option value="جوشقان قالی">جوشقان قالی</option>
                                        <option value="بویین ومیاندشت">بویین ومیاندشت</option>
                                        <option value="کرکوند">کرکوند</option>
                                        <option value="درچه">درچه</option>
                                        <option value="انارک">انارک</option>
                                        <option value="دولت آباد">دولت آباد</option>
                                        <option value="ایمانشهر">ایمانشهر</option>
                                        <option value="گرگاب">گرگاب</option>
                                        <option value="حسن اباد">حسن اباد</option>
                                        <option value="سده لنجان">سده لنجان</option>
                                        <option value="حبیب آباد">حبیب آباد</option>
                                        <option value="بهاران شهر">بهاران شهر</option>
                                        <option value="میمه">میمه</option>
                                        <option value="تودشک">تودشک</option>
                                        <option value="گلشهر">گلشهر</option>
                                        <option value="رضوانشهر">رضوانشهر</option>
                                        <option value="داران">داران</option>
                                        <option value="علویجه">علویجه</option>
                                        <option value="نیک آباد">نیک آباد</option>
                                        <option value="مشکات">مشکات</option>
                                        <option value="آران وبیدگل">آران وبیدگل</option>
                                        <option value="خوانسار">خوانسار</option>
                                        <option value="نجف آباد">نجف آباد</option>
                                        <option value="منظریه">منظریه</option>
                                        <option value="فرخی">فرخی</option>
                                        <option value="دیزیچه">دیزیچه</option>
                                        <option value="اژیه">اژیه</option>
                                        <option value="زاینده رود">زاینده رود</option>
                                        <option value="خورزوق">خورزوق</option>
                                        <option value="قهدریجان">قهدریجان</option>
                                        <option value="شاهین شهر">شاهین شهر</option>
                                        <option value="بهارستان">بهارستان</option>
                                        <option value="چمگردان">چمگردان</option>
                                        <option value="دهاقان">دهاقان</option>
                                        <option value="برف انبار">برف انبار</option>
                                        <option value="بادرود">بادرود</option>
                                        <option value="کوهپایه">کوهپایه</option>
                                        <option value="گلپایگان">گلپایگان</option>
                                        <option value="عسگران">عسگران</option>
                                        <option value="حنا">حنا</option>
                                        <option value="کهریزسنگ">کهریزسنگ</option>
                                        <option value="مهاباد">مهاباد</option>
                                        <option value="کامو و چوگان">کامو و چوگان</option>
                                        <option value="افوس">افوس</option>
                                        <option value="زیباشهر">زیباشهر</option>
                                        <option value="کوشک">کوشک</option>
                                        <option value="نایین">نایین</option>
                                        <option value="سین">سین</option>
                                        <option value="زازران">زازران</option>
                                        <option value="مبارکه">مبارکه</option>
                                        <option value="ورزنه">ورزنه</option>
                                        <option value="ورنامخواست">ورنامخواست</option>
                                        <option value="شاپورآباد">شاپورآباد</option>
                                        <option value="فلاورجان">فلاورجان</option>
                                        <option value="وزوان">وزوان</option>
                                        <option value="باغ بهادران">باغ بهادران</option>
                                        <option value="اصفهان">اصفهان</option>
                                        <option value="چادگان">چادگان</option>
                                        <option value="دامنه">دامنه</option>
                                        <option value="نطنز">نطنز</option>
                                        <option value="محمدآباد">محمدآباد</option>
                                        <option value="اصفهان">اصفهان</option>
                                        <option value="نیاسر">نیاسر</option>
                                        <option value="نوش آباد">نوش آباد</option>
                                        <option value="کمه">کمه</option>
                                        <option value="جوزدان">جوزدان</option>
                                        <option value="قمصر">قمصر</option>
                                        <option value="جندق">جندق</option>
                                        <option value="طالخونچه">طالخونچه</option>
                                        <option value="خمینی شهر">خمینی شهر</option>
                                        <option value="باغشاد">باغشاد</option>
                                        <option value="دستگرد">دستگرد</option>
                                        <option value="ابریشم">ابریشم</option>
                                    </select>
                                    <select name="city" id="city" class="city4">
                                        <option value="کازرون">کازرون</option>
                                        <option value="کارزین (فتح آباد)">کارزین (فتح آباد)</option>
                                        <option value="فدامی">فدامی</option>
                                        <option value="خومه زار">خومه زار</option>
                                        <option value="سلطان شهر">سلطان شهر</option>
                                        <option value="فیروزآباد">فیروزآباد</option>
                                        <option value="دبیران">دبیران</option>
                                        <option value="باب انار">باب انار</option>
                                        <option value="رامجرد">رامجرد</option>
                                        <option value="سروستان">سروستان</option>
                                        <option value="قره بلاغ">قره بلاغ</option>
                                        <option value="ارسنجان">ارسنجان</option>
                                        <option value="دژکرد">دژکرد</option>
                                        <option value="بیرم">بیرم</option>
                                        <option value="دهرم">دهرم</option>
                                        <option value="شیراز">شیراز</option>
                                        <option value="ایزدخواست">ایزدخواست</option>
                                        <option value="علامرودشت">علامرودشت</option>
                                        <option value="اوز">اوز</option>
                                        <option value="وراوی">وراوی</option>
                                        <option value="بیضا">بیضا</option>
                                        <option value="نی ریز">نی ریز</option>
                                        <option value="کنارتخته">کنارتخته</option>
                                        <option value="امام شهر">امام شهر</option>
                                        <option value="جهرم">جهرم</option>
                                        <option value="بابامنیر">بابامنیر</option>
                                        <option value="گراش">گراش</option>
                                        <option value="فسا">فسا</option>
                                        <option value="شهرپیر">شهرپیر</option>
                                        <option value="حسن اباد">حسن اباد</option>
                                        <option value="کامفیروز">کامفیروز</option>
                                        <option value="خنج">خنج</option>
                                        <option value="خانه زنیان">خانه زنیان</option>
                                        <option value="استهبان">استهبان</option>
                                        <option value="بوانات">بوانات</option>
                                        <option value="لطیفی">لطیفی</option>
                                        <option value="فراشبند">فراشبند</option>
                                        <option value="زرقان">زرقان</option>
                                        <option value="صغاد">صغاد</option>
                                        <option value="اشکنان">اشکنان</option>
                                        <option value="قایمیه">قایمیه</option>
                                        <option value="گله دار">گله دار</option>
                                        <option value="دوبرجی">دوبرجی</option>
                                        <option value="آباده طشک">آباده طشک</option>
                                        <option value="خرامه">خرامه</option>
                                        <option value="میمند">میمند</option>
                                        <option value="افزر">افزر</option>
                                        <option value="دوزه">دوزه</option>
                                        <option value="سیدان">سیدان</option>
                                        <option value="کوپن">کوپن</option>
                                        <option value="زاهدشهر">زاهدشهر</option>
                                        <option value="قادراباد">قادراباد</option>
                                        <option value="سده">سده</option>
                                        <option value="بنارویه">بنارویه</option>
                                        <option value="سعادت شهر">سعادت شهر</option>
                                        <option value="شهرصدرا">شهرصدرا</option>
                                        <option value="سورمق">سورمق</option>
                                        <option value="حسامی">حسامی</option>
                                        <option value="جویم">جویم</option>
                                        <option value="خوزی">خوزی</option>
                                        <option value="اردکان">اردکان</option>
                                        <option value="قطرویه">قطرویه</option>
                                        <option value="نودان">نودان</option>
                                        <option value="مبارک آباددیز">مبارک آباددیز</option>
                                        <option value="داراب">داراب</option>
                                        <option value="نورآباد">نورآباد</option>
                                        <option value="کوار">کوار</option>
                                        <option value="نوبندگان">نوبندگان</option>
                                        <option value="حاجی آباد">حاجی آباد</option>
                                        <option value="خاوران">خاوران</option>
                                        <option value="مرودشت">مرودشت</option>
                                        <option value="کوهنجان">کوهنجان</option>
                                        <option value="ششده">ششده</option>
                                        <option value="مزایجان">مزایجان</option>
                                        <option value="ایج">ایج</option>
                                        <option value="خور">خور</option>
                                        <option value="نوجین">نوجین</option>
                                        <option value="لپویی">لپویی</option>
                                        <option value="بهمن">بهمن</option>
                                        <option value="اهل">اهل</option>
                                        <option value="خشت">خشت</option>
                                        <option value="مهر">مهر</option>
                                        <option value="جنت شهر">جنت شهر</option>
                                        <option value="مشکان">مشکان</option>
                                        <option value="بالاده">بالاده</option>
                                        <option value="قیر">قیر</option>
                                        <option value="قطب آباد">قطب آباد</option>
                                        <option value="خانیمن">خانیمن</option>
                                        <option value="مصیری">مصیری</option>
                                        <option value="میانشهر">میانشهر</option>
                                        <option value="صفاشهر">صفاشهر</option>
                                        <option value="اقلید">اقلید</option>
                                        <option value="عمادده">عمادده</option>
                                        <option value="مادرسلیمان">مادرسلیمان</option>
                                        <option value="داریان">داریان</option>
                                        <option value="رونیز">رونیز</option>
                                        <option value="کره ای">کره ای</option>
                                        <option value="لار">لار</option>
                                        <option value="اسیر">اسیر</option>
                                        <option value="هماشهر">هماشهر</option>
                                        <option value="آباده">آباده</option>
                                        <option value="لامرد">لامرد</option>
                                    </select>
                                    <select name="city" id="city" class="city5">
                                        <option value="هفتگل">هفتگل</option>
                                        <option value="بیدروبه">بیدروبه</option>
                                        <option value="شاوور">شاوور</option>
                                        <option value="حمزه">حمزه</option>
                                        <option value="گتوند">گتوند</option>
                                        <option value="شرافت">شرافت</option>
                                        <option value="منصوریه">منصوریه</option>
                                        <option value="زهره">زهره</option>
                                        <option value="رامهرمز">رامهرمز</option>
                                        <option value="بندرامام خمینی">بندرامام خمینی</option>
                                        <option value="کوت عبداله">کوت عبداله</option>
                                        <option value="میداود">میداود</option>
                                        <option value="چغامیش">چغامیش</option>
                                        <option value="ملاثانی">ملاثانی</option>
                                        <option value="چم گلک">چم گلک</option>
                                        <option value="حر">حر</option>
                                        <option value="شمس آباد">شمس آباد</option>
                                        <option value="آبژدان">آبژدان</option>
                                        <option value="چویبده">چویبده</option>
                                        <option value="مسجدسلیمان">مسجدسلیمان</option>
                                        <option value="مقاومت">مقاومت</option>
                                        <option value="ترکالکی">ترکالکی</option>
                                        <option value="دارخوین">دارخوین</option>
                                        <option value="سردشت">سردشت</option>
                                        <option value="لالی">لالی</option>
                                        <option value="کوت سیدنعیم">کوت سیدنعیم</option>
                                        <option value="حمیدیه">حمیدیه</option>
                                        <option value="دهدز">دهدز</option>
                                        <option value="قلعه تل">قلعه تل</option>
                                        <option value="میانرود">میانرود</option>
                                        <option value="رفیع">رفیع</option>
                                        <option value="اندیمشک">اندیمشک</option>
                                        <option value="الوان">الوان</option>
                                        <option value="سالند">سالند</option>
                                        <option value="صالح شهر">صالح شهر</option>
                                        <option value="اروندکنار">اروندکنار</option>
                                        <option value="سرداران">سرداران</option>
                                        <option value="تشان">تشان</option>
                                        <option value="شادگان">شادگان</option>
                                        <option value="بندرماهشهر">بندرماهشهر</option>
                                        <option value="جایزان">جایزان</option>
                                        <option value="بستان">بستان</option>
                                        <option value="ویس">ویس</option>
                                        <option value="اهواز">اهواز</option>
                                        <option value="فتح المبین">فتح المبین</option>
                                        <option value="شهر امام">شهر امام</option>
                                        <option value="قلعه خواجه">قلعه خواجه</option>
                                        <option value="حسینیه">حسینیه</option>
                                        <option value="گلگیر">گلگیر</option>
                                        <option value="مینوشهر">مینوشهر</option>
                                        <option value="سماله">سماله</option>
                                        <option value="شوشتر">شوشتر</option>
                                        <option value="بهبهان">بهبهان</option>
                                        <option value="هندیجان">هندیجان</option>
                                        <option value="ابوحمیظه">ابوحمیظه</option>
                                        <option value="آغاجاری">آغاجاری</option>
                                        <option value="ایذه">ایذه</option>
                                        <option value="صیدون">صیدون</option>
                                        <option value="سیاه منصور">سیاه منصور</option>
                                        <option value="هویزه">هویزه</option>
                                        <option value="آزادی">آزادی</option>
                                        <option value="شوش">شوش</option>
                                        <option value="دزفول">دزفول</option>
                                        <option value="جنت مکان">جنت مکان</option>
                                        <option value="آبادان">آبادان</option>
                                        <option value="گوریه">گوریه</option>
                                        <option value="خرمشهر">خرمشهر</option>
                                        <option value="مشراگه">مشراگه</option>
                                        <option value="خنافره">خنافره</option>
                                        <option value="چمران">چمران</option>
                                        <option value="امیدیه">امیدیه</option>
                                        <option value="سوسنگرد">سوسنگرد</option>
                                        <option value="شیبان">شیبان</option>
                                        <option value="الهایی">الهایی</option>
                                        <option value="باغ ملک">باغ ملک</option>
                                        <option value="صفی آباد">صفی آباد</option>
                                        <option value="رامشیر">رامشیر</option>
                                    </select>
                                    <select name="city" id="city" class="city6">
                                        <option value="کشکسرای">کشکسرای</option>
                                        <option value="سهند">سهند</option>
                                        <option value="سیس">سیس</option>
                                        <option value="دوزدوزان">دوزدوزان</option>
                                        <option value="تیمورلو">تیمورلو</option>
                                        <option value="صوفیان">صوفیان</option>
                                        <option value="سردرود">سردرود</option>
                                        <option value="هادیشهر">هادیشهر</option>
                                        <option value="هشترود">هشترود</option>
                                        <option value="زرنق">زرنق</option>
                                        <option value="ترکمانچای">ترکمانچای</option>
                                        <option value="ورزقان">ورزقان</option>
                                        <option value="تسوج">تسوج</option>
                                        <option value="زنوز">زنوز</option>
                                        <option value="ایلخچی">ایلخچی</option>
                                        <option value="شرفخانه">شرفخانه</option>
                                        <option value="مهربان">مهربان</option>
                                        <option value="مبارک شهر">مبارک شهر</option>
                                        <option value="تیکمه داش">تیکمه داش</option>
                                        <option value="باسمنج">باسمنج</option>
                                        <option value="سیه رود">سیه رود</option>
                                        <option value="میانه">میانه</option>
                                        <option value="خمارلو">خمارلو</option>
                                        <option value="خواجه">خواجه</option>
                                        <option value="بناب مرند">بناب مرند</option>
                                        <option value="قره آغاج">قره آغاج</option>
                                        <option value="وایقان">وایقان</option>
                                        <option value="مراغه">مراغه</option>
                                        <option value="ممقان">ممقان</option>
                                        <option value="خامنه">خامنه</option>
                                        <option value="خسروشاه">خسروشاه</option>
                                        <option value="لیلان">لیلان</option>
                                        <option value="نظرکهریزی">نظرکهریزی</option>
                                        <option value="اهر">اهر</option>
                                        <option value="بخشایش">بخشایش</option>
                                        <option value="آقکند">آقکند</option>
                                        <option value="جوان قلعه">جوان قلعه</option>
                                        <option value="کلیبر">کلیبر</option>
                                        <option value="مرند">مرند</option>
                                        <option value="اسکو">اسکو</option>
                                        <option value="شندآباد">شندآباد</option>
                                        <option value="شربیان">شربیان</option>
                                        <option value="گوگان">گوگان</option>
                                        <option value="بستان آباد">بستان آباد</option>
                                        <option value="تبریز">تبریز</option>
                                        <option value="جلفا">جلفا</option>
                                        <option value="اچاچی">اچاچی</option>
                                        <option value="هریس">هریس</option>
                                        <option value="یامچی">یامچی</option>
                                        <option value="خاروانا">خاروانا</option>
                                        <option value="کوزه کنان">کوزه کنان</option>
                                        <option value="خداجو(خراجو)">خداجو(خراجو)</option>
                                        <option value="آذرشهر">آذرشهر</option>
                                        <option value="شبستر">شبستر</option>
                                        <option value="سراب">سراب</option>
                                        <option value="ملکان">ملکان</option>
                                        <option value="بناب">بناب</option>
                                        <option value="هوراند">هوراند</option>
                                        <option value="کلوانق">کلوانق</option>
                                        <option value="ترک">ترک</option>
                                        <option value="عجب شیر">عجب شیر</option>
                                        <option value="آبش احمد">آبش احمد</option>
                                    </select>
                                    <select name="city" id="city" class="city7">
                                        <option value="گلوگاه">گلوگاه</option>
                                        <option value="پل سفید">پل سفید</option>
                                        <option value="دابودشت">دابودشت</option>
                                        <option value="چالوس">چالوس</option>
                                        <option value="کیاسر">کیاسر</option>
                                        <option value="بهنمیر">بهنمیر</option>
                                        <option value="تنکابن">تنکابن</option>
                                        <option value="کلاردشت">کلاردشت</option>
                                        <option value="ایزدشهر">ایزدشهر</option>
                                        <option value="گتاب">گتاب</option>
                                        <option value="سلمان شهر">سلمان شهر</option>
                                        <option value="ارطه">ارطه</option>
                                        <option value="امیرکلا">امیرکلا</option>
                                        <option value="کوهی خیل">کوهی خیل</option>
                                        <option value="پایین هولار">پایین هولار</option>
                                        <option value="گزنک">گزنک</option>
                                        <option value="محمودآباد">محمودآباد</option>
                                        <option value="رامسر">رامسر</option>
                                        <option value="نوشهر">نوشهر</option>
                                        <option value="خلیل شهر">خلیل شهر</option>
                                        <option value="کیاکلا">کیاکلا</option>
                                        <option value="نور">نور</option>
                                        <option value="مرزیکلا">مرزیکلا</option>
                                        <option value="فریدونکنار">فریدونکنار</option>
                                        <option value="زیرآب">زیرآب</option>
                                        <option value="امامزاده عبدالله">امامزاده عبدالله</option>
                                        <option value="هچیرود">هچیرود</option>
                                        <option value="فریم">فریم</option>
                                        <option value="هادی شهر">هادی شهر</option>
                                        <option value="نشتارود">نشتارود</option>
                                        <option value="پول">پول</option>
                                        <option value="بهشهر">بهشهر</option>
                                        <option value="کلارآباد">کلارآباد</option>
                                        <option value="بلده">بلده</option>
                                        <option value="بابل">بابل</option>
                                        <option value="جویبار">جویبار</option>
                                        <option value="آلاشت">آلاشت</option>
                                        <option value="آمل">آمل</option>
                                        <option value="نکا">نکا</option>
                                        <option value="کتالم وسادات شهر">کتالم وسادات شهر</option>
                                        <option value="بابلسر">بابلسر</option>
                                        <option value="شیرود">شیرود</option>
                                        <option value="شیرگاه">شیرگاه</option>
                                        <option value="رویان">رویان</option>
                                        <option value="زرگرمحله">زرگرمحله</option>
                                        <option value="عباس اباد">عباس اباد</option>
                                        <option value="قایم شهر">قایم شهر</option>
                                        <option value="خوش رودپی">خوش رودپی</option>
                                        <option value="مرزن آباد">مرزن آباد</option>
                                        <option value="ساری">ساری</option>
                                        <option value="رینه">رینه</option>
                                        <option value="سرخرود">سرخرود</option>
                                        <option value="خرم آباد">خرم آباد</option>
                                        <option value="کجور">کجور</option>
                                        <option value="رستمکلا">رستمکلا</option>
                                        <option value="سورک">سورک</option>
                                        <option value="چمستان">چمستان</option>
                                    </select>
                                    <select name="city" id="city" class="city8">
                                        <option value="تازه شهر">تازه شهر</option>
                                        <option value="نالوس">نالوس</option>
                                        <option value="ایواوغلی">ایواوغلی</option>
                                        <option value="شاهین دژ">شاهین دژ</option>
                                        <option value="گردکشانه">گردکشانه</option>
                                        <option value="باروق">باروق</option>
                                        <option value="سیلوانه">سیلوانه</option>
                                        <option value="بازرگان">بازرگان</option>
                                        <option value="نازک علیا">نازک علیا</option>
                                        <option value="ربط">ربط</option>
                                        <option value="تکاب">تکاب</option>
                                        <option value="دیزج دیز">دیزج دیز</option>
                                        <option value="سیمینه">سیمینه</option>
                                        <option value="نوشین">نوشین</option>
                                        <option value="میاندوآب">میاندوآب</option>
                                        <option value="مرگنلر">مرگنلر</option>
                                        <option value="سلماس">سلماس</option>
                                        <option value="آواجیق">آواجیق</option>
                                        <option value="قطور">قطور</option>
                                        <option value="محمودآباد">محمودآباد</option>
                                        <option value="خوی">خوی</option>
                                        <option value="نقده">نقده</option>
                                        <option value="سرو">سرو</option>
                                        <option value="خلیفان">خلیفان</option>
                                        <option value="پلدشت">پلدشت</option>
                                        <option value="میرآباد">میرآباد</option>
                                        <option value="اشنویه">اشنویه</option>
                                        <option value="زرآباد">زرآباد</option>
                                        <option value="بوکان">بوکان</option>
                                        <option value="پیرانشهر">پیرانشهر</option>
                                        <option value="چهاربرج">چهاربرج</option>
                                        <option value="قوشچی">قوشچی</option>
                                        <option value="شوط">شوط</option>
                                        <option value="ماکو">ماکو</option>
                                        <option value="سیه چشمه">سیه چشمه</option>
                                        <option value="سردشت">سردشت</option>
                                        <option value="کشاورز">کشاورز</option>
                                        <option value="فیرورق">فیرورق</option>
                                        <option value="محمدیار">محمدیار</option>
                                        <option value="ارومیه">ارومیه</option>
                                        <option value="مهاباد">مهاباد</option>
                                        <option value="قره ضیاءالدین">قره ضیاءالدین</option>
                                    </select>
                                    <select name="city" id="city" class="city9">
                                        <option value="کهنوج">کهنوج</option>
                                        <option value="بلوک">بلوک</option>
                                        <option value="پاریز">پاریز</option>
                                        <option value="گنبکی">گنبکی</option>
                                        <option value="زنگی آباد">زنگی آباد</option>
                                        <option value="بم">بم</option>
                                        <option value="خانوک">خانوک</option>
                                        <option value="کیانشهر">کیانشهر</option>
                                        <option value="جوپار">جوپار</option>
                                        <option value="عنبرآباد">عنبرآباد</option>
                                        <option value="جوزم">جوزم</option>
                                        <option value="نظام شهر">نظام شهر</option>
                                        <option value="لاله زار">لاله زار</option>
                                        <option value="کشکوییه">کشکوییه</option>
                                        <option value="زیدآباد">زیدآباد</option>
                                        <option value="هنزا">هنزا</option>
                                        <option value="چترود">چترود</option>
                                        <option value="جبالبارز">جبالبارز</option>
                                        <option value="سیرجان">سیرجان</option>
                                        <option value="رودبار">رودبار</option>
                                        <option value="کرمان">کرمان</option>
                                        <option value="بافت">بافت</option>
                                        <option value="صفاییه">صفاییه</option>
                                        <option value="منوجان">منوجان</option>
                                        <option value="اندوهجرد">اندوهجرد</option>
                                        <option value="هجدک">هجدک</option>
                                        <option value="خورسند">خورسند</option>
                                        <option value="امین شهر">امین شهر</option>
                                        <option value="بردسیر">بردسیر</option>
                                        <option value="رفسنجان">رفسنجان</option>
                                        <option value="هماشهر">هماشهر</option>
                                        <option value="محمدآباد">محمدآباد</option>
                                        <option value="اختیارآباد">اختیارآباد</option>
                                        <option value="بروات">بروات</option>
                                        <option value="ریحان">ریحان</option>
                                        <option value="کوهبنان">کوهبنان</option>
                                        <option value="ماهان">ماهان</option>
                                        <option value="دوساری">دوساری</option>
                                        <option value="دهج">دهج</option>
                                        <option value="فاریاب">فاریاب</option>
                                        <option value="گلزار">گلزار</option>
                                        <option value="بهرمان">بهرمان</option>
                                        <option value="بلورد">بلورد</option>
                                        <option value="فهرج">فهرج</option>
                                        <option value="کاظم آباد">کاظم آباد</option>
                                        <option value="جیرفت">جیرفت</option>
                                        <option value="نجف شهر">نجف شهر</option>
                                        <option value="قلعه گنج">قلعه گنج</option>
                                        <option value="باغین">باغین</option>
                                        <option value="بزنجان">بزنجان</option>
                                        <option value="زرند">زرند</option>
                                        <option value="نودژ">نودژ</option>
                                        <option value="گلباف">گلباف</option>
                                        <option value="راور">راور</option>
                                        <option value="خاتون اباد">خاتون اباد</option>
                                        <option value="نرماشیر">نرماشیر</option>
                                        <option value="دشتکار">دشتکار</option>
                                        <option value="مس سرچشمه">مس سرچشمه</option>
                                        <option value="خواجو شهر">خواجو شهر</option>
                                        <option value="رابر">رابر</option>
                                        <option value="راین">راین</option>
                                        <option value="درب بهشت">درب بهشت</option>
                                        <option value="یزدان شهر">یزدان شهر</option>
                                        <option value="زهکلوت">زهکلوت</option>
                                        <option value="محی آباد">محی آباد</option>
                                        <option value="مردهک">مردهک</option>
                                        <option value="شهداد">شهداد</option>
                                        <option value="ارزوییه">ارزوییه</option>
                                        <option value="نگار">نگار</option>
                                        <option value="شهربابک">شهربابک</option>
                                        <option value="انار">انار</option>
                                    </select>
                                    <select name="city" id="city" class="city10">
                                        <option value="محمدی">محمدی</option>
                                        <option value="شهرک علی اکبر">شهرک علی اکبر</option>
                                        <option value="بنجار">بنجار</option>
                                        <option value="گلمورتی">گلمورتی</option>
                                        <option value="نگور">نگور</option>
                                        <option value="راسک">راسک</option>
                                        <option value="بنت">بنت</option>
                                        <option value="قصرقند">قصرقند</option>
                                        <option value="جالق">جالق</option>
                                        <option value="هیدوچ">هیدوچ</option>
                                        <option value="نوک آباد">نوک آباد</option>
                                        <option value="زهک">زهک</option>
                                        <option value="بمپور">بمپور</option>
                                        <option value="پیشین">پیشین</option>
                                        <option value="گشت">گشت</option>
                                        <option value="محمدآباد">محمدآباد</option>
                                        <option value="زاهدان">زاهدان</option>
                                        <option value="زابلی">زابلی</option>
                                        <option value="چاه بهار">چاه بهار</option>
                                        <option value="زرآباد">زرآباد</option>
                                        <option value="بزمان">بزمان</option>
                                        <option value="اسپکه">اسپکه</option>
                                        <option value="فنوج">فنوج</option>
                                        <option value="سراوان">سراوان</option>
                                        <option value="ادیمی">ادیمی</option>
                                        <option value="زابل">زابل</option>
                                        <option value="دوست محمد">دوست محمد</option>
                                        <option value="ایرانشهر">ایرانشهر</option>
                                        <option value="سرباز">سرباز</option>
                                        <option value="سیرکان">سیرکان</option>
                                        <option value="میرجاوه">میرجاوه</option>
                                        <option value="نصرت آباد">نصرت آباد</option>
                                        <option value="سوران">سوران</option>
                                        <option value="خاش">خاش</option>
                                        <option value="کنارک">کنارک</option>
                                        <option value="محمدان">محمدان</option>
                                        <option value="نیک شهر">نیک شهر</option>
                                    </select>
                                    <select name="city" id="city" class="city11">
                                        <option value="چهارباغ">چهارباغ</option>
                                        <option value="آسارا">آسارا</option>
                                        <option value="کرج">کرج</option>
                                        <option value="طالقان">طالقان</option>
                                        <option value="شهرجدیدهشتگرد">شهرجدیدهشتگرد</option>
                                        <option value="محمدشهر">محمدشهر</option>
                                        <option value="مشکین دشت">مشکین دشت</option>
                                        <option value="نظرآباد">نظرآباد</option>
                                        <option value="هشتگرد">هشتگرد</option>
                                        <option value="ماهدشت">ماهدشت</option>
                                        <option value="اشتهارد">اشتهارد</option>
                                        <option value="کوهسار">کوهسار</option>
                                        <option value="گرمدره">گرمدره</option>
                                        <option value="تنکمان">تنکمان</option>
                                        <option value="گلسار">گلسار</option>
                                        <option value="کمال شهر">کمال شهر</option>
                                        <option value="فردیس">فردیس</option>
                                    </select>
                                    <select name="city" id="city" class="city12">
                                        <option value="منجیل">منجیل</option>
                                        <option value="شلمان">شلمان</option>
                                        <option value="خشکبیجار">خشکبیجار</option>
                                        <option value="ماکلوان">ماکلوان</option>
                                        <option value="سنگر">سنگر</option>
                                        <option value="مرجقل">مرجقل</option>
                                        <option value="لیسار">لیسار</option>
                                        <option value="رضوانشهر">رضوانشهر</option>
                                        <option value="رحیم آباد">رحیم آباد</option>
                                        <option value="لوندویل">لوندویل</option>
                                        <option value="احمدسرگوراب">احمدسرگوراب</option>
                                        <option value="لوشان">لوشان</option>
                                        <option value="اطاقور">اطاقور</option>
                                        <option value="لشت نشاء">لشت نشاء</option>
                                        <option value="فومن">فومن</option>
                                        <option value="چوبر">چوبر</option>
                                        <option value="بازار جمعه">بازار جمعه</option>
                                        <option value="کلاچای">کلاچای</option>
                                        <option value="بندرانزلی">بندرانزلی</option>
                                        <option value="املش">املش</option>
                                        <option value="رستم آباد">رستم آباد</option>
                                        <option value="لاهیجان">لاهیجان</option>
                                        <option value="توتکابن">توتکابن</option>
                                        <option value="لنگرود">لنگرود</option>
                                        <option value="کوچصفهان">کوچصفهان</option>
                                        <option value="صومعه سرا">صومعه سرا</option>
                                        <option value="اسالم">اسالم</option>
                                        <option value="دیلمان">دیلمان</option>
                                        <option value="رودسر">رودسر</option>
                                        <option value="کیاشهر">کیاشهر</option>
                                        <option value="شفت">شفت</option>
                                        <option value="رودبار">رودبار</option>
                                        <option value="کومله">کومله</option>
                                        <option value="رشت">رشت</option>
                                        <option value="ماسوله">ماسوله</option>
                                        <option value="خمام">خمام</option>
                                        <option value="ماسال">ماسال</option>
                                        <option value="واجارگاه">واجارگاه</option>
                                        <option value="هشتپر (تالش)">هشتپر (تالش)</option>
                                        <option value="پره سر">پره سر</option>
                                        <option value="بره سر">بره سر</option>
                                        <option value="آستارا">آستارا</option>
                                        <option value="رودبنه">رودبنه</option>
                                        <option value="جیرنده">جیرنده</option>
                                        <option value="چاف و چمخاله">چاف و چمخاله</option>
                                        <option value="لولمان">لولمان</option>
                                        <option value="گوراب زرمیخ">گوراب زرمیخ</option>
                                        <option value="حویق">حویق</option>
                                        <option value="سیاهکل">سیاهکل</option>
                                        <option value="چابکسر">چابکسر</option>
                                        <option value="آستانه اشرفیه">آستانه اشرفیه</option>
                                        <option value="رانکوه">رانکوه</option>
                                    </select>
                                    <select name="city" id="city" class="city13">
                                        <option value="سنقر">سنقر</option>
                                        <option value="شاهو">شاهو</option>
                                        <option value="بانوره">بانوره</option>
                                        <option value="تازه آباد">تازه آباد</option>
                                        <option value="هلشی">هلشی</option>
                                        <option value="جوانرود">جوانرود</option>
                                        <option value="قصرشیرین">قصرشیرین</option>
                                        <option value="نوسود">نوسود</option>
                                        <option value="کرند">کرند</option>
                                        <option value="کوزران">کوزران</option>
                                        <option value="بیستون">بیستون</option>
                                        <option value="حمیل">حمیل</option>
                                        <option value="گیلانغرب">گیلانغرب</option>
                                        <option value="سطر">سطر</option>
                                        <option value="روانسر">روانسر</option>
                                        <option value="پاوه">پاوه</option>
                                        <option value="ازگله">ازگله</option>
                                        <option value="کرمانشاه">کرمانشاه</option>
                                        <option value="میان راهان">میان راهان</option>
                                        <option value="کنگاور">کنگاور</option>
                                        <option value="سرپل ذهاب">سرپل ذهاب</option>
                                        <option value="ریجاب">ریجاب</option>
                                        <option value="باینگان">باینگان</option>
                                        <option value="هرسین">هرسین</option>
                                        <option value="اسلام آبادغرب">اسلام آبادغرب</option>
                                        <option value="سرمست">سرمست</option>
                                        <option value="سومار">سومار</option>
                                        <option value="نودشه">نودشه</option>
                                        <option value="گهواره">گهواره</option>
                                        <option value="رباط">رباط</option>
                                        <option value="صحنه">صحنه</option>
                                        <option value="گودین">گودین</option>
                                    </select>
                                    <select name="city" id="city" class="city14">
                                        <option value="سیمین شهر">سیمین شهر</option>
                                        <option value="مزرعه">مزرعه</option>
                                        <option value="رامیان">رامیان</option>
                                        <option value="فراغی">فراغی</option>
                                        <option value="گنبدکاووس">گنبدکاووس</option>
                                        <option value="کردکوی">کردکوی</option>
                                        <option value="مراوه">مراوه</option>
                                        <option value="بندرترکمن">بندرترکمن</option>
                                        <option value="نگین شهر">نگین شهر</option>
                                        <option value="آق قلا">آق قلا</option>
                                        <option value="سرخنکلاته">سرخنکلاته</option>
                                        <option value="گالیکش">گالیکش</option>
                                        <option value="سنگدوین">سنگدوین</option>
                                        <option value="دلند">دلند</option>
                                        <option value="بندرگز">بندرگز</option>
                                        <option value="نوده خاندوز">نوده خاندوز</option>
                                        <option value="مینودشت">مینودشت</option>
                                        <option value="گرگان">گرگان</option>
                                        <option value="گمیش تپه">گمیش تپه</option>
                                        <option value="علی اباد">علی اباد</option>
                                        <option value="خان ببین">خان ببین</option>
                                        <option value="کلاله">کلاله</option>
                                        <option value="اینچه برون">اینچه برون</option>
                                        <option value="فاضل آباد">فاضل آباد</option>
                                        <option value="تاتارعلیا">تاتارعلیا</option>
                                        <option value="نوکنده">نوکنده</option>
                                        <option value="آزادشهر">آزادشهر</option>
                                        <option value="انبارآلوم">انبارآلوم</option>
                                        <option value="جلین">جلین</option>
                                    </select>
                                    <select class="city15" name="city" id="city">
                                        <option value="بیکاء">بیکاء</option>
                                        <option value="تیرور">تیرور</option>
                                        <option value="گروک">گروک</option>
                                        <option value="قشم">قشم</option>
                                        <option value="کوشکنار">کوشکنار</option>
                                        <option value="کیش">کیش</option>
                                        <option value="سرگز">سرگز</option>
                                        <option value="بندرعباس">بندرعباس</option>
                                        <option value="زیارتعلی">زیارتعلی</option>
                                        <option value="سندرک">سندرک</option>
                                        <option value="کوهستک">کوهستک</option>
                                        <option value="لمزان">لمزان</option>
                                        <option value="رویدر">رویدر</option>
                                        <option value="قلعه قاضی">قلعه قاضی</option>
                                        <option value="فارغان">فارغان</option>
                                        <option value="ابوموسی">ابوموسی</option>
                                        <option value="هشتبندی">هشتبندی</option>
                                        <option value="سردشت">سردشت</option>
                                        <option value="درگهان">درگهان</option>
                                        <option value="پارسیان">پارسیان</option>
                                        <option value="کنگ">کنگ</option>
                                        <option value="جناح">جناح</option>
                                        <option value="تازیان پایین">تازیان پایین</option>
                                        <option value="دهبارز">دهبارز</option>
                                        <option value="میناب">میناب</option>
                                        <option value="سیریک">سیریک</option>
                                        <option value="سوزا">سوزا</option>
                                        <option value="خمیر">خمیر</option>
                                        <option value="چارک">چارک</option>
                                        <option value="حاجی اباد">حاجی اباد</option>
                                        <option value="فین">فین</option>
                                        <option value="بندرجاسک">بندرجاسک</option>
                                        <option value="گوهران">گوهران</option>
                                        <option value="هرمز">هرمز</option>
                                        <option value="دشتی">دشتی</option>
                                        <option value="بندرلنگه">بندرلنگه</option>
                                        <option value="بستک">بستک</option>
                                        <option value="تخت">تخت</option>
                                    </select>
                                    <select class="city16" name="city" id="city">
                                        <option value="چالانچولان">چالانچولان</option>
                                        <option value="بیران شهر">بیران شهر</option>
                                        <option value="ویسیان">ویسیان</option>
                                        <option value="شول آباد">شول آباد</option>
                                        <option value="پلدختر">پلدختر</option>
                                        <option value="کوهدشت">کوهدشت</option>
                                        <option value="هفت چشمه">هفت چشمه</option>
                                        <option value="بروجرد">بروجرد</option>
                                        <option value="الشتر">الشتر</option>
                                        <option value="مومن آباد">مومن آباد</option>
                                        <option value="دورود">دورود</option>
                                        <option value="زاغه">زاغه</option>
                                        <option value="چقابل">چقابل</option>
                                        <option value="الیگودرز">الیگودرز</option>
                                        <option value="معمولان">معمولان</option>
                                        <option value="کوهنانی">کوهنانی</option>
                                        <option value="نورآباد">نورآباد</option>
                                        <option value="سپیددشت">سپیددشت</option>
                                        <option value="سراب دوره">سراب دوره</option>
                                        <option value="ازنا">ازنا</option>
                                        <option value="گراب">گراب</option>
                                        <option value="خرم آباد">خرم آباد</option>
                                        <option value="اشترینان">اشترینان</option>
                                        <option value="فیروزآباد">فیروزآباد</option>
                                        <option value="درب گنبد">درب گنبد</option>
                                    </select>
                                    <select class="city17" name="city" id="city">
                                        <option value="زنگنه">زنگنه</option>
                                        <option value="دمق">دمق</option>
                                        <option value="سرکان">سرکان</option>
                                        <option value="آجین">آجین</option>
                                        <option value="جورقان">جورقان</option>
                                        <option value="برزول">برزول</option>
                                        <option value="فامنین">فامنین</option>
                                        <option value="سامن">سامن</option>
                                        <option value="بهار">بهار</option>
                                        <option value="فرسفج">فرسفج</option>
                                        <option value="شیرین سو">شیرین سو</option>
                                        <option value="مریانج">مریانج</option>
                                        <option value="فیروزان">فیروزان</option>
                                        <option value="قروه درجزین">قروه درجزین</option>
                                        <option value="ازندریان">ازندریان</option>
                                        <option value="لالجین">لالجین</option>
                                        <option value="گل تپه">گل تپه</option>
                                        <option value="گیان">گیان</option>
                                        <option value="ملایر">ملایر</option>
                                        <option value="صالح آباد">صالح آباد</option>
                                        <option value="تویسرکان">تویسرکان</option>
                                        <option value="اسدآباد">اسدآباد</option>
                                        <option value="همدان">همدان</option>
                                        <option value="نهاوند">نهاوند</option>
                                        <option value="رزن">رزن</option>
                                        <option value="جوکار">جوکار</option>
                                        <option value="مهاجران">مهاجران</option>
                                        <option value="کبودرآهنگ">کبودرآهنگ</option>
                                        <option value="قهاوند">قهاوند</option>
                                    </select>
                                    <select class="city18" name="city" id="city">
                                        <option value="قروه">قروه</option>
                                        <option value="توپ آغاج">توپ آغاج</option>
                                        <option value="سروآباد">سروآباد</option>
                                        <option value="بویین سفلی">بویین سفلی</option>
                                        <option value="زرینه">زرینه</option>
                                        <option value="دلبران">دلبران</option>
                                        <option value="سنندج">سنندج</option>
                                        <option value="یاسوکند">یاسوکند</option>
                                        <option value="موچش">موچش</option>
                                        <option value="بانه">بانه</option>
                                        <option value="مریوان">مریوان</option>
                                        <option value="سریش آباد">سریش آباد</option>
                                        <option value="صاحب">صاحب</option>
                                        <option value="دهگلان">دهگلان</option>
                                        <option value="بابارشانی">بابارشانی</option>
                                        <option value="دیواندره">دیواندره</option>
                                        <option value="برده رشه">برده رشه</option>
                                        <option value="شویشه">شویشه</option>
                                        <option value="بیجار">بیجار</option>
                                        <option value="اورامان تخت">اورامان تخت</option>
                                        <option value="کانی سور">کانی سور</option>
                                        <option value="کانی دینار">کانی دینار</option>
                                        <option value="دزج">دزج</option>
                                        <option value="سقز">سقز</option>
                                        <option value="بلبان آباد">بلبان آباد</option>
                                        <option value="پیرتاج">پیرتاج</option>
                                        <option value="کامیاران">کامیاران</option>
                                        <option value="آرمرده">آرمرده</option>
                                        <option value="چناره">چناره</option>
                                    </select>
                                    <select class="city19" name="city" id="city">
                                        <option value="آستانه">آستانه</option>
                                        <option value="خنجین">خنجین</option>
                                        <option value="نراق">نراق</option>
                                        <option value="کمیجان">کمیجان</option>
                                        <option value="آشتیان">آشتیان</option>
                                        <option value="رازقان">رازقان</option>
                                        <option value="مهاجران">مهاجران</option>
                                        <option value="غرق آباد">غرق آباد</option>
                                        <option value="خنداب">خنداب</option>
                                        <option value="قورچی باشی">قورچی باشی</option>
                                        <option value="خشکرود">خشکرود</option>
                                        <option value="ساروق">ساروق</option>
                                        <option value="محلات">محلات</option>
                                        <option value="شازند">شازند</option>
                                        <option value="ساوه">ساوه</option>
                                        <option value="میلاجرد">میلاجرد</option>
                                        <option value="تفرش">تفرش</option>
                                        <option value="زاویه">زاویه</option>
                                        <option value="اراک">اراک</option>
                                        <option value="توره">توره</option>
                                        <option value="نوبران">نوبران</option>
                                        <option value="فرمهین">فرمهین</option>
                                        <option value="دلیجان">دلیجان</option>
                                        <option value="پرندک">پرندک</option>
                                        <option value="کارچان">کارچان</option>
                                        <option value="نیمور">نیمور</option>
                                        <option value="هندودر">هندودر</option>
                                        <option value="آوه">آوه</option>
                                        <option value="جاورسیان">جاورسیان</option>
                                        <option value="خمین">خمین</option>
                                        <option value="مامونیه">مامونیه</option>
                                        <option value="داودآباد">داودآباد</option>
                                        <option value="شهباز">شهباز</option>
                                    </select>
                                    <select class="city20" name="city" id="city">
                                        <option value="کهک">کهک</option>
                                        <option value="قم">قم</option>
                                        <option value="سلفچگان">سلفچگان</option>
                                        <option value="جعفریه">جعفریه</option>
                                        <option value="قنوات">قنوات</option>
                                        <option value="دستجرد">دستجرد</option>
                                    </select>
                                    <select class="city21" name="city" id="city">
                                        <option value="سگزآباد">سگزآباد</option>
                                        <option value="بیدستان">بیدستان</option>
                                        <option value="کوهین">کوهین</option>
                                        <option value="رازمیان">رازمیان</option>
                                        <option value="خرمدشت">خرمدشت</option>
                                        <option value="آبگرم">آبگرم</option>
                                        <option value="شال">شال</option>
                                        <option value="شریفیه">شریفیه</option>
                                        <option value="اقبالیه">اقبالیه</option>
                                        <option value="نرجه">نرجه</option>
                                        <option value="ارداق">ارداق</option>
                                        <option value="الوند">الوند</option>
                                        <option value="خاکعلی">خاکعلی</option>
                                        <option value="سیردان">سیردان</option>
                                        <option value="ضیاڈآباد">ضیاڈآباد</option>
                                        <option value="بویین زهرا">بویین زهرا</option>
                                        <option value="محمدیه">محمدیه</option>
                                        <option value="محمودآبادنمونه">محمودآبادنمونه</option>
                                        <option value="معلم کلایه">معلم کلایه</option>
                                        <option value="اسفرورین">اسفرورین</option>
                                        <option value="آوج">آوج</option>
                                        <option value="دانسفهان">دانسفهان</option>
                                        <option value="آبیک">آبیک</option>
                                        <option value="قزوین">قزوین</option>
                                        <option value="تاکستان">تاکستان</option>
                                    </select>
                                    <select class="city22" name="city" id="city">
                                        <option value="پارس آباد">پارس آباد</option>
                                        <option value="فخراباد">فخراباد</option>
                                        <option value="کلور">کلور</option>
                                        <option value="نیر">نیر</option>
                                        <option value="اردبیل">اردبیل</option>
                                        <option value="اسلام اباد">اسلام اباد</option>
                                        <option value="تازه کندانگوت">تازه کندانگوت</option>
                                        <option value="مشگین شهر">مشگین شهر</option>
                                        <option value="جعفرآباد">جعفرآباد</option>
                                        <option value="نمین">نمین</option>
                                        <option value="اصلاندوز">اصلاندوز</option>
                                        <option value="مرادلو">مرادلو</option>
                                        <option value="خلخال">خلخال</option>
                                        <option value="کوراییم">کوراییم</option>
                                        <option value="هیر">هیر</option>
                                        <option value="گیوی">گیوی</option>
                                        <option value="گرمی">گرمی</option>
                                        <option value="لاهرود">لاهرود</option>
                                        <option value="هشتجین">هشتجین</option>
                                        <option value="عنبران">عنبران</option>
                                        <option value="تازه کند">تازه کند</option>
                                        <option value="قصابه">قصابه</option>
                                        <option value="رضی">رضی</option>
                                        <option value="سرعین">سرعین</option>
                                        <option value="بیله سوار">بیله سوار</option>
                                        <option value="آبی بیگلو">آبی بیگلو</option>
                                    </select>
                                    <select class="city23" name="city" id="city">
                                        <option value="پارس آباد">پارس آباد</option>
                                        <option value="ریز">ریز</option>
                                        <option value="برازجان">برازجان</option>
                                        <option value="بندرریگ">بندرریگ</option>
                                        <option value="اهرم">اهرم</option>
                                        <option value="دوراهک">دوراهک</option>
                                        <option value="خورموج">خورموج</option>
                                        <option value="نخل تقی">نخل تقی</option>
                                        <option value="کلمه">کلمه</option>
                                        <option value="بندردیلم">بندردیلم</option>
                                        <option value="وحدتیه">وحدتیه</option>
                                        <option value="بنک">بنک</option>
                                        <option value="چغادک">چغادک</option>
                                        <option value="بندردیر">بندردیر</option>
                                        <option value="کاکی">کاکی</option>
                                        <option value="جم">جم</option>
                                        <option value="دالکی">دالکی</option>
                                        <option value="بندرگناوه">بندرگناوه</option>
                                        <option value="آباد">آباد</option>
                                        <option value="آبدان">آبدان</option>
                                        <option value="خارک">خارک</option>
                                        <option value="شنبه">شنبه</option>
                                        <option value="بوشکان">بوشکان</option>
                                        <option value="انارستان">انارستان</option>
                                        <option value="شبانکاره">شبانکاره</option>
                                        <option value="سیراف">سیراف</option>
                                        <option value="دلوار">دلوار</option>
                                        <option value="بردستان">بردستان</option>
                                        <option value="بادوله">بادوله</option>
                                        <option value="عسلویه">عسلویه</option>
                                        <option value="تنگ ارم">تنگ ارم</option>
                                        <option value="امام حسن">امام حسن</option>
                                        <option value="سعد آباد">سعد آباد</option>
                                        <option value="بندرکنگان">بندرکنگان</option>
                                        <option value="بوشهر">بوشهر</option>
                                        <option value="بردخون">بردخون</option>
                                        <option value="آب پخش">آب پخش</option>
                                    </select>
                                    <select class="city24" name="city" id="city">
                                        <option value="مروست">مروست</option>
                                        <option value="مهردشت">مهردشت</option>
                                        <option value="حمیدیا">حمیدیا</option>
                                        <option value="تفت">تفت</option>
                                        <option value="اشکذر">اشکذر</option>
                                        <option value="ندوشن">ندوشن</option>
                                        <option value="یزد">یزد</option>
                                        <option value="عقدا">عقدا</option>
                                        <option value="بهاباد">بهاباد</option>
                                        <option value="ابرکوه">ابرکوه</option>
                                        <option value="زارچ">زارچ</option>
                                        <option value="نیر">نیر</option>
                                        <option value="اردکان">اردکان</option>
                                        <option value="هرات">هرات</option>
                                        <option value="بفروییه">بفروییه</option>
                                        <option value="شاهدیه">شاهدیه</option>
                                        <option value="بافق">بافق</option>
                                        <option value="خضرآباد">خضرآباد</option>
                                        <option value="میبد">میبد</option>
                                        <option value="مهریز">مهریز</option>
                                        <option value="احمدآباد">احمدآباد</option>
                                    </select>
                                    <select class="city25" name="city" id="city">
                                        <option value="سجاس">سجاس</option>
                                        <option value="زرین رود">زرین رود</option>
                                        <option value="آب بر">آب بر</option>
                                        <option value="ارمغانخانه">ارمغانخانه</option>
                                        <option value="کرسف">کرسف</option>
                                        <option value="هیدج">هیدج</option>
                                        <option value="سلطانیه">سلطانیه</option>
                                        <option value="خرمدره">خرمدره</option>
                                        <option value="نیک پی">نیک پی</option>
                                        <option value="قیدار">قیدار</option>
                                        <option value="ابهر">ابهر</option>
                                        <option value="دندی">دندی</option>
                                        <option value="حلب">حلب</option>
                                        <option value="نوربهار">نوربهار</option>
                                        <option value="گرماب">گرماب</option>
                                        <option value="چورزق">چورزق</option>
                                        <option value="زنجان">زنجان</option>
                                        <option value="سهرورد">سهرورد</option>
                                        <option value="صایین قلعه">صایین قلعه</option>
                                        <option value="ماه نشان">ماه نشان</option>
                                        <option value="زرین آباد">زرین آباد</option>
                                    </select>
                                    <select class="city26" name="city" id="city">
                                        <option value="وردنجان">وردنجان</option>
                                        <option value="گوجان">گوجان</option>
                                        <option value="گهرو">گهرو</option>
                                        <option value="سورشجان">سورشجان</option>
                                        <option value="سرخون">سرخون</option>
                                        <option value="شهرکرد">شهرکرد</option>
                                        <option value="منج">منج</option>
                                        <option value="بروجن">بروجن</option>
                                        <option value="پردنجان">پردنجان</option>
                                        <option value="سامان">سامان</option>
                                        <option value="فرخ شهر">فرخ شهر</option>
                                        <option value="صمصامی">صمصامی</option>
                                        <option value="طاقانک">طاقانک</option>
                                        <option value="کاج">کاج</option>
                                        <option value="نقنه">نقنه</option>
                                        <option value="لردگان">لردگان</option>
                                        <option value="باباحیدر">باباحیدر</option>
                                        <option value="دستنا">دستنا</option>
                                        <option value="سودجان">سودجان</option>
                                        <option value="بازفت">بازفت</option>
                                        <option value="هفشجان">هفشجان</option>
                                        <option value="سردشت">سردشت</option>
                                        <option value="فرادبنه">فرادبنه</option>
                                        <option value="چلیچه">چلیچه</option>
                                        <option value="بن">بن</option>
                                        <option value="فارسان">فارسان</option>
                                        <option value="شلمزار">شلمزار</option>
                                        <option value="نافچ">نافچ</option>
                                        <option value="دشتک">دشتک</option>
                                        <option value="بلداجی">بلداجی</option>
                                        <option value="آلونی">آلونی</option>
                                        <option value="گندمان">گندمان</option>
                                        <option value="جونقان">جونقان</option>
                                        <option value="ناغان">ناغان</option>
                                        <option value="هارونی">هارونی</option>
                                        <option value="چلگرد">چلگرد</option>
                                        <option value="کیان">کیان</option>
                                        <option value="اردل">اردل</option>
                                        <option value="سفیددشت">سفیددشت</option>
                                        <option value="مال خلیفه">مال خلیفه</option>
                                    </select>
                                    <select class="city27" name="city" id="city">
                                        <option value="چناران شهر">چناران شهر</option>
                                        <option value="راز">راز</option>
                                        <option value="پیش قلعه">پیش قلعه</option>
                                        <option value="قوشخانه">قوشخانه</option>
                                        <option value="شوقان">شوقان</option>
                                        <option value="اسفراین">اسفراین</option>
                                        <option value="گرمه">گرمه</option>
                                        <option value="قاضی">قاضی</option>
                                        <option value="شیروان">شیروان</option>
                                        <option value="حصارگرمخان">حصارگرمخان</option>
                                        <option value="آشخانه">آشخانه</option>
                                        <option value="تیتکانلو">تیتکانلو</option>
                                        <option value="جاجرم">جاجرم</option>
                                        <option value="بجنورد">بجنورد</option>
                                        <option value="درق">درق</option>
                                        <option value="آوا">آوا</option>
                                        <option value="زیارت">زیارت</option>
                                        <option value="سنخواست">سنخواست</option>
                                        <option value="صفی آباد">صفی آباد</option>
                                        <option value="ایور">ایور</option>
                                        <option value="فاروج">فاروج</option>
                                        <option value="لوجلی">لوجلی</option>
                                    </select>
                                    <select class="city28" name="city" id="city">
                                        <option value="اسلامیه">اسلامیه</option>
                                        <option value="شوسف">شوسف</option>
                                        <option value="قاین">قاین</option>
                                        <option value="عشق آباد">عشق آباد</option>
                                        <option value="طبس مسینا">طبس مسینا</option>
                                        <option value="ارسک">ارسک</option>
                                        <option value="آیسک">آیسک</option>
                                        <option value="نیمبلوک">نیمبلوک</option>
                                        <option value="دیهوک">دیهوک</option>
                                        <option value="سربیشه">سربیشه</option>
                                        <option value="محمدشهر">محمدشهر</option>
                                        <option value="بیرجند">بیرجند</option>
                                        <option value="فردوس">فردوس</option>
                                        <option value="نهبندان">نهبندان</option>
                                        <option value="اسفدن">اسفدن</option>
                                        <option value="گزیک">گزیک</option>
                                        <option value="حاجی آباد">حاجی آباد</option>
                                        <option value="سه قلعه">سه قلعه</option>
                                        <option value="آرین شهر">آرین شهر</option>
                                        <option value="مود">مود</option>
                                        <option value="خوسف">خوسف</option>
                                        <option value="قهستان">قهستان</option>
                                        <option value="بشرویه">بشرویه</option>
                                        <option value="سرایان">سرایان</option>
                                        <option value="خضری دشت بیاض">خضری دشت بیاض</option>
                                        <option value="طبس">طبس</option>
                                        <option value="اسدیه">اسدیه</option>
                                        <option value="زهان">زهان</option>
                                    </select>
                                    <select class="city29" name="city" id="city">
                                        <option value="گراب سفلی">گراب سفلی</option>
                                        <option value="لنده">لنده</option>
                                        <option value="سی سخت">سی سخت</option>
                                        <option value="دهدشت">دهدشت</option>
                                        <option value="یاسوج">یاسوج</option>
                                        <option value="سرفاریاب">سرفاریاب</option>
                                        <option value="دوگنبدان">دوگنبدان</option>
                                        <option value="چیتاب">چیتاب</option>
                                        <option value="لیکک">لیکک</option>
                                        <option value="دیشموک">دیشموک</option>
                                        <option value="مادوان">مادوان</option>
                                        <option value="باشت">باشت</option>
                                        <option value="پاتاوه">پاتاوه</option>
                                        <option value="قلعه رییسی">قلعه رییسی</option>
                                        <option value="مارگون">مارگون</option>
                                        <option value="چرام">چرام</option>
                                        <option value="سوق">سوق</option>
                                    </select>
                                    <select class="city30" name="city" id="city">
                                        <option value="ایوانکی">ایوانکی</option>
                                        <option value="مجن">مجن</option>
                                        <option value="دامغان">دامغان</option>
                                        <option value="سرخه">سرخه</option>
                                        <option value="مهدی شهر">مهدی شهر</option>
                                        <option value="شاهرود">شاهرود</option>
                                        <option value="سمنان">سمنان</option>
                                        <option value="کهن آباد">کهن آباد</option>
                                        <option value="گرمسار">گرمسار</option>
                                        <option value="کلاته خیج">کلاته خیج</option>
                                        <option value="دیباج">دیباج</option>
                                        <option value="درجزین">درجزین</option>
                                        <option value="رودیان">رودیان</option>
                                        <option value="بسطام">بسطام</option>
                                        <option value="امیریه">امیریه</option>
                                        <option value="میامی">میامی</option>
                                        <option value="شهمیرزاد">شهمیرزاد</option>
                                        <option value="بیارجمند">بیارجمند</option>
                                        <option value="کلاته">کلاته</option>
                                        <option value="آرادان">آرادان</option>
                                    </select>
                                    <select class="city31" name="city" id="city">
                                        <option value="آبدانان">آبدانان</option>
                                        <option value="شباب">شباب</option>
                                        <option value="موسیان">موسیان</option>
                                        <option value="بدره">بدره</option>
                                        <option value="ایلام">ایلام</option>
                                        <option value="ایوان">ایوان</option>
                                        <option value="مهران">مهران</option>
                                        <option value="آسمان آباد">آسمان آباد</option>
                                        <option value="پهله">پهله</option>
                                        <option value="مهر">مهر</option>
                                        <option value="سراب باغ">سراب باغ</option>
                                        <option value="بلاوه">بلاوه</option>
                                        <option value="میمه">میمه</option>
                                        <option value="دره شهر">دره شهر</option>
                                        <option value="ارکواز">ارکواز</option>
                                        <option value="مورموری">مورموری</option>
                                        <option value="توحید">توحید</option>
                                        <option value="دهلران">دهلران</option>
                                        <option value="لومار">لومار</option>
                                        <option value="چوار">چوار</option>
                                        <option value="زرنه">زرنه</option>
                                        <option value="صالح آباد">صالح آباد</option>
                                        <option value="سرابله">سرابله</option>
                                        <option value="ماژین">ماژین</option>
                                        <option value="دلگشا">دلگشا</option>
                                    </select>
                                    <div id="validation-city"></div>
                                </div>
                            </div>
                            <div class="item">
                                <label for="address">آدرس کامل شما <span>*</span></label>
                                <input id="address" type="text" placeholder="{{__('messages.address')}}" name="address" value="{{$address?$address->address:''}}">
                                <div id="validation-address"></div>
                            </div>
                            <div class="items">
                                <div class="item">
                                    <label for="plaque">پلاک <span>*</span></label>
                                    <input id="plaque" type="text" placeholder="{{__('messages.plaque_address')}}" name="plaque" value="{{$address?$address->plaque:''}}">
                                    <div id="validation-plaque"></div>
                                </div>
                                <div class="item">
                                    <label for="unit">{{__('messages.unit_address')}}</label>
                                    <input id="unit" type="text" placeholder="{{__('messages.unit_address')}}" name="unit" value="{{$address?$address->unit:''}}">
                                </div>
                                <div class="item">
                                    <label for="post">کد پستی <span>*</span></label>
                                    <input id="post" type="text" placeholder="{{__('messages.post_address')}}" name="post" value="{{$address?$address->post:''}}">
                                    <div id="validation-post"></div>
                                </div>
                            </div>
                            <div class="items">
                                <div class="item">
                                    <label for="name">نام گیرنده<span>*</span></label>
                                    <input id="name" type="text" placeholder="{{__('messages.name_address')}}" name="name" value="{{$address?$address->name:''}}">
                                    <div id="validation-name"></div>
                                </div>
                                <div class="item">
                                    <label for="number">شماره تماس<span>*</span></label>
                                    <input id="number" type="text" placeholder="{{__('messages.number_address')}}" name="number" value="{{$address?$address->number:''}}">
                                    <div id="validation-number"></div>
                                </div>
                                <div class="item">
                                    <label for="natural">کد ملی<span>*</span></label>
                                    <input id="natural" type="text" placeholder="کد ملی" name="natural" value="{{$address?$address->natural:''}}">
                                    <div id="validation-natural"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bottomAddress">
                        <button id="showBtnAddress">ثبت اطلاعات آدرس</button>
                    </div>
                </form>
                <div class="abouts">
                    <div class="title2"><span>توضیحات سفارش</span></div>
                    <div class="data">
                        <div class="name">توضیحات سفارش (اختیاری) :</div>
                        <textarea name="body2" placeholder="توضیحات دلخواه"></textarea>
                    </div>
                </div>
            </div>
            <div class="left">
                <div class="box">
                    <div class="title2"><span>سفارش شما</span></div>
                    <div class="title4">
                        <div class="name">محصولات</div>
                        <div class="body">مجموع</div>
                    </div>
                    @foreach($getCart[0] as $item)
                        <div class="options">
                            <div class="option">
                                <div class="title3">{{$item['title']}}</div>
                                <div class="title3">{{number_format($item['price']*$item['count'])}}</div>
                            </div>
                        </div>
                    @endforeach
                    <div class="price">
                        <div class="title3">سبد خرید</div>
                        <div class="body">{{number_format($getCart[2])}}</div>
                    </div>
                    <div class="priceF">
                        <div class="title3">مبلغ نهایی</div>
                        <div class="body priceF2" data-price="{{(int)$getCart[2]}}">{{number_format($getCart[2] + $getCart[1])}}</div>
                    </div>
                    <div class="carriers">
                        <div class="title">روش ارسال</div>
                        <select name="carrier_id1">
                            @foreach(\App\Models\Carrier::get() as $item)
                                <option value="{{$item->id}}" data-price="{{$item->price}}">{{$item->name}} {{number_format($item->price)}} تومان</option>
                            @endforeach
                        </select>
                    </div>
                    <ul class="methods">
                        <li>
                            <button class="active" id="shop">
                                <span>{{__('messages.method_cart1')}}</span>
                            </button>
                        </li>
                        <li>
                            <button id="wallet-shop">
                                <span>{{__('messages.method_cart3')}} ({{number_format($myCharge)}} تومان)</span>
                                <br>
                                <span style="font-size: .9rem;color: red;font-weight: 300;">
                                    پرداخت
                                    {{number_format($myCharge)}}
                                    تومان از کیف پول
                                    @if((int)$getCart[2] + (int)$getCart[1] - $myCharge >= 1)
                                        و پرداخت
                                        {{number_format((int)$getCart[2] + (int)$getCart[1] - $myCharge)}} تومان از درگاه
                                    @endif
                                </span>
                            </button>
                        </li>
                    </ul>
                    <div class="gatePay">
                        @if($statusSadad)
                            <label class="gateItem" id="5">
                                <img src="/img/sadad.png" alt="method">
                                سدااد
                            </label>
                        @endif
                        @if($statusBeh)
                            <label class="gateItem" id="4">
                                <img src="/img/behpardakht.png" alt="method">
                                به پرداخت
                            </label>
                        @endif
                        @if($idpayStatus)
                            <label class="gateItem" id="3">
                                <img src="/img/idpay.png" alt="method">
                                آیدی پی
                            </label>
                        @endif
                        @if($nextpayStatus)
                            <label class="gateItem" id="2">
                                <img src="/img/nextpay.png" alt="method">
                                نکست پی
                            </label>
                        @endif
                        @if($zarinpalStatus && $getCart[2] <= 50000000)
                            <label class="gateItem active" id="0">
                                <img src="/img/zarinpal.svg" alt="method">
                                زرین پال
                            </label>
                        @endif
                        @if($zibalStatus)
                            <label class="gateItem" id="1">
                                <img src="/img/zibal.png" alt="method">
                                زیبال
                            </label>
                        @endif
                        @if($statusAsan)
                            <label class="gateItem" id="6">
                                <img src="/img/asanPardakht.jpg" alt="method">
                            </label>
                        @endif
                        @if($statusPasargad)
                            <label class="gateItem" id="7">
                                <img src="/img/pasargad.png" alt="method">
                            </label>
                        @endif
                        @if($statusSaman)
                            <label class="gateItem" id="8">
                                <img src="/img/saman.jpg" alt="method">
                                سامان
                            </label>
                        @endif
                        @if($statusAzki)
                            <label class="gateItem" id="9">
                                <img src="/img/azki.webp" alt="method">
                                ازکی
                            </label>
                        @endif
                        @if($statusSetare)
                            <label class="gateItem" id="10">
                                <img src="/img/setare.png" alt="method">
                                ستاره یک
                            </label>
                        @endif
                        @if($statusDigi)
                            <label class="gateItem" id="11">
                                <img src="/img/digi.png" alt="method">
                                دیجی پی
                            </label>
                        @endif
                        @if($statusKaman)
                            <label class="gateItem" id="12">
                                <img src="/img/kaman.webp" alt="method">
                                کمان لند
                            </label>
                        @endif
                        @if($statusBolon)
                            <label class="gateItem" id="13">
                                <img src="/img/balon.png" alt="method">
                                بالون
                            </label>
                        @endif
                        @if($statusVib)
                            <label class="gateItem" id="14">
                                <img src="/img/vib.png" alt="method">
                                وایب
                            </label>
                        @endif
                        @if($statusVency)
                            <label class="gateItem" id="15">
                                <img src="/img/vency.svg" alt="method">
                                ونسی
                            </label>
                        @endif
                        @if($statusTechno)
                            <label class="gateItem" id="16">
                                <img src="/img/techno.png" alt="method">
                                تکنو
                            </label>
                        @endif
                    </div>
                    <label for="ss12" class="rule">
                        <input id="ss12" type="checkbox" name="rule">
                        <a href="/page/rule">قونین و شرایط</a>
                        را خواندم و تایید میکنم
                    </label>
                    <form action="/shop" method="get" class="nextItem gg1">
                        @csrf
                        <input type="hidden" name="gateway" value="{{\App\Models\Setting::where('key' , 'choicePay')->pluck('value')->first()}}">
                        <input type="hidden" name="body" value="">
                        <input type="hidden" name="carrier_id" value="">
                        <button class="move" disabled>پرداخت و تسویه</button>
                    </form>
                    <div class="nextItem gg2" style="display: none">
                        <button class="move">پرداخت و تسویه</button>
                    </div>
                    <div class="nextItem gg3" style="display: none">
                        <button class="move">پرداخت و تسویه</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="pop1 b1">
            <div class="show">
                <div class="title">
                    <div class="title2">خرید از بالون</div>
                    <i class="closeCounseling">
                        <svg class="icon">
                            <use xlink:href="#cancel"></use>
                        </svg>
                    </i>
                </div>
                <div class="data level1">
                    <div class="item">
                        <div class="name">کد ملی</div>
                        <input type="text" name="natural" placeholder="کد ملی شما">
                    </div>
                    <p></p>
                    <div class="buttons">
                        <button>استعلام</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="pop1 b2">
            <div class="show">
                <div class="title">
                    <div class="title2">خرید از بتا</div>
                    <i class="closeCounseling">
                        <svg class="icon">
                            <use xlink:href="#cancel"></use>
                        </svg>
                    </i>
                </div>
                <div class="data level1">
                    <div class="item">
                        <div class="name">کد ملی</div>
                        <input type="text" name="natural" placeholder="کد ملی شما">
                    </div>
                    <p></p>
                    <div class="buttons">
                        <button>استعلام</button>
                    </div>
                </div>
            </div>
        </div>
    </main>
    @foreach($widget as $item)
        @if($item['name'] == 'لیست')
            <div class="myWidget">
                @include('home.index.listsIndex' , ['data' => $item])
            </div>
        @endif
    @endforeach
@endsection

@section('linkPage')
    <link rel="stylesheet" href="/css/jquery.toast.min.css"/>
@endsection
@section('scriptPage')
    <script src="/js/jquery.toast.min.js"></script>
    <script>
        $(document).ready(function () {
            var address1 = {!! json_encode($address, JSON_HEX_TAG) !!};
            var natural = '';
            $(".CreateAddress select[name='state']").val(address1?address1.state:'تهران');
            getState(address1?address1.state:'تهران',address1?address1.city:'تهران');
            $('.rule input').prop('checked',false);
            $('.rule input').change(function (){
                if($(this).prop('checked') == true){
                    $(".nextItem button").prop('disabled',false);
                }else{
                    $(".nextItem button").prop('disabled',true);
                }
            });
            $('.closeCounseling').click(function(){
                $(".b1").hide();
            })
            $('.discount1 button').click(function(){
                var form = {
                    "_token": "{{ csrf_token() }}",
                    discount:$(".discount1 input[name='discount']").val(),
                };
                $.ajax({
                    url: "/check-discount-cart",
                    type: "post",
                    data: form,
                    success: function (data) {
                        if(data == 'no'){
                            $.toast({
                                text: 'کد تخفیف وجود ندارد', // Text that is to be shown in the toast
                                heading: 'ناموفق', // Optional heading to be shown on the toast
                                icon: 'error', // Type of toast icon
                                showHideTransition: 'fade', // fade, slide or plain
                                allowToastClose: true, // Boolean value true or false
                                hideAfter: 3000, // false to make it sticky or number representing the miliseconds as time after which toast needs to be hidden
                                stack: 5, // false if there should be only one toast at a time or a number representing the maximum number of toasts to be shown at a time
                                position: 'bottom-left', // bottom-left or bottom-right or bottom-center or top-left or top-right or top-center or mid-center or an object representing the left, right, top, bottom values
                                textAlign: 'left',  // Text alignment i.e. left, right or center
                                loader: true,  // Whether to show loader or not. True by default
                                loaderBg: 'rgba(255,0,0,0.47)',  // Background color of the toast loader
                            });
                        }else{
                            $.toast({
                                text: 'کد تخفیف ثبت شد', // Text that is to be shown in the toast
                                heading: 'موفق', // Optional heading to be shown on the toast
                                icon: 'success', // Type of toast icon
                                showHideTransition: 'fade', // fade, slide or plain
                                allowToastClose: true, // Boolean value true or false
                                hideAfter: 3000, // false to make it sticky or number representing the miliseconds as time after which toast needs to be hidden
                                stack: 5, // false if there should be only one toast at a time or a number representing the maximum number of toasts to be shown at a time
                                position: 'bottom-left', // bottom-left or bottom-right or bottom-center or top-left or top-right or top-center or mid-center or an object representing the left, right, top, bottom values
                                textAlign: 'left',  // Text alignment i.e. left, right or center
                                loader: true,  // Whether to show loader or not. True by default
                                loaderBg: '#9EC600',  // Background color of the toast loader
                            });
                            $(".priceF2").text(makePrice(data));
                        }
                    },
                });
            })
            $(".allCheckoutIndex textarea[name='body2']").on('keyup' , function(e){
                $(".allCheckoutIndex input[name='body']").val($(".allCheckoutIndex textarea[name='body2']").val());
            });
            $(".CreateAddress select[name='state']").on('change' , function(e){
                getState(this.value,'');
                $("input[name='state1']").val(this.value);
            });
            $(".CreateAddress select[name='city']").on('change' , function(e){
                $("input[name='city1']").val(this.value);
            });
            $('.allCheckoutIndex .level1 button').on('click' , function(){
                natural = $(".allCheckoutIndex .level1 input[name='natural']").val();
                $(this).text('صبرکنید...');
                var form = {
                    "_token": "{{ csrf_token() }}",
                    natural: natural,
                    price: $(".priceF2").attr('data-price'),
                };
                $.ajax({
                    url: "/natural-bolon",
                    type: "post",
                    data: form,
                    success: function (data) {
                        if(data[0] == 1) {
                            alert('متاسفانه موجودی کافی ندارید.')
                            $(".b1").hide();
                            $(this).text('استعلام');
                            $(".level1").text(makePrice(data[1]) + ' تومان')
                        }else if(data[0] == 0) {
                            alert('سامانه قطع است.')
                            $(".b1").hide();
                            $(this).text('استعلام');
                        }else{
                            $(".level1").remove();
                            $(".b1 .show").append(
                                `<div class="data level2">
                    <div class="item">
                        <div class="name">کد تایید هویت</div>
                        <input type="text" name="code" placeholder="کد ارسال شده به شماره شما">
                    </div>
                    <p>${makePrice(data[1])} تومان</p>
                    <div class="buttons">
                        <button>تایید و ثبت نهایی</button>
                    </div>
                </div>`
                            );
                        }
                    }
                });
            })
            $(document).on('click' , '.b1 .level2 button' , function(){
                $(this).text('صبرکنید...');
                var form = {
                    "_token": "{{ csrf_token() }}",
                    natural: natural,
                    carrier: $("select[name='carrier_id1']").val(),
                    code: $(".b1 .show input[name='code']").val(),
                };
                $.ajax({
                    url: "/send-bolon",
                    type: "post",
                    data: form,
                    success: function (data) {
                        window.location.href = '/gate-pay?pay=' + data;
                    }
                });
            })
            changeP();
            $("select[name='carrier_id1']").on('change' , function(){
                changeP();
            })
            $('.allCheckoutIndex .gg2').on('click' , function(){
                $(".b1").show();
            })
            $('.allCheckoutIndex .gg3').on('click' , function(){
                $(".b2").show();
            })
            function changeP(){
                $("input[name='carrier_id']").val($("select[name='carrier_id1']").val());
                $(".priceF2").text(makePrice(parseInt($(".priceF2").attr('data-price'))+parseInt($("select[name='carrier_id1']").find('option:selected').attr('data-price'))));
            }
            $('.allCheckoutIndex .left .gateItem').on('click' , function(){
                $.each($('.allCheckoutIndex .left .gateItem'),function(){
                    $(this).attr('class' , 'gateItem');
                })
                $(this).attr('class','gateItem active');
                $(".allCheckoutIndex .left form input[name='gateway']").val($(this).attr('id'));
                if($(this).attr('id') == 13){
                    $(".nextItem").show();
                    $(".gg1,.gg3").hide();
                }else{
                    $(".nextItem").show();
                    $(".gg2,.gg3").hide();
                }
            });
            $('.discount1 p').on('click' , function(){
                $(".discount1 .codes").toggle()
            })
            $('.methods button').on('click' , function(){
                $.each($('.methods button'),function(){
                    $(this).attr('class' , 'unActive');
                })
                $(".left form").attr('action' , '/' + $(this).attr('id'));
                $(this).attr('class' , 'active');
                if($(this).attr('id') == 'wallet-shop'){
                    $(".left .gatePay .gateItem[id='13'],.left .gatePay .gateItem[id='10'],.left .gatePay .gateItem[id='12'],.left .gatePay .gateItem[id='14']").hide();
                }else{
                    $(".left .gatePay .gateItem[id='13'],.left .gatePay .gateItem[id='10'],.left .gatePay .gateItem[id='12'],.left .gatePay .gateItem[id='14']").show();
                }
            })

            setTimeout(() => {
                var originLat =  address1.lat ?? 35.710141;
                var originLng =  address1.lng ?? 51.366920;
                var app = new Mapp({
                    element: '#map1',
                    presets: {
                        latlng: {
                            lat: originLat,
                            lng: originLng,
                        },
                        zoom: 13,
                    },
                    apiKey: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVmMDFmMDMxOTE2MmYxMWM4YjFmNjAxZGEzZWM0ZTcyZDI1ZjMxY2ZiZDI0NjM2MzE0OGJjZWI5NzcwN2VlOTM4NjBhZmNlOTc0NTZlMjk2In0.eyJhdWQiOiIxNjMzMCIsImp0aSI6IjVmMDFmMDMxOTE2MmYxMWM4YjFmNjAxZGEzZWM0ZTcyZDI1ZjMxY2ZiZDI0NjM2MzE0OGJjZWI5NzcwN2VlOTM4NjBhZmNlOTc0NTZlMjk2IiwiaWF0IjoxNjk1MjQwOTI3LCJuYmYiOjE2OTUyNDA5MjcsImV4cCI6MTY5NzU3MzcyNywic3ViIjoiIiwic2NvcGVzIjpbImJhc2ljIl19.ggya0Flw4c5RI67geif-boTPM15vM4nIRF1fKflZbHxVHdv6TMhRQkR_cyCOnoL8M-JzsHrVloiHZqb64_laAMNqNMjjWPqcmPFo-AOEqG3v_-8k1bPfxM_6iZEsARwmdVqwByG9KEKleJ8lZR6TKuSWQtDPw_9wTP39bWAw7udKLCw_cDRo_ZOYUSqFXwypDi3YEvQNXP1YaTDvTIQbZRWMgwDEJ1d9F2oymRcWnJyTSiW_KCDeYl7kQr74pGafUAGtgGskB2Weh2MZYJzsdM-9ioX1lBHbnDCdaHY8rLzDeLPu7gFSgK6HQ2CXQlbkrY90a-f2G69GG1iqGsPHjA'
                });
                if(address1.lat){
                    app.addMarker({
                        latlng: {
                            lat: originLat,
                            lng: originLng
                        },
                        on: {
                            click: function(){
                                app.removeMarkers({
                                    group: app.groups.features.markers,
                                });
                                setTimeout( function(){
                                    $("form .mapp-modal").remove();
                                }  , 1000 );
                            },
                        },
                    });
                }
                app.addLayers();
                app.map.on('click', function(e) {
                    app.showReverseGeocode({
                        state: {
                            latlng: {
                                lat: e.latlng.lat,
                                lng: e.latlng.lng
                            },
                            zoom: 16
                        }
                    });
                    $("form input[name='lat']").val(e.latlng.lat);
                    $("form input[name='lng']").val(e.latlng.lng);
                    app.addMarker({
                        name: 'advanced-marker',
                        latlng: {
                            lat: e.latlng.lat,
                            lng: e.latlng.lng
                        },
                        zoom: 16,
                        draggable: true,
                        popup: true,
                        on: {
                            click: function(){
                                app.removeMarkers({
                                    group: app.groups.features.markers,
                                });
                            },
                        },
                    });
                    setTimeout( function(){
                        $("form .mapp-modal").remove();
                    }  , 1000 );
                });
            }, 3000);
            function getState(value1,city1){
                $("select[name='city']").hide();
                if(value1 == 'تهران'){
                    $('.CreateAddress .city1').show();
                    $('.CreateAddress .city1').val(city1);
                }
                if(value1 == 'خراسان رضوی'){
                    $('.CreateAddress .city2').show();
                    $('.CreateAddress .city2').val(city1);
                }
                if(value1 == 'اصفهان'){
                    $('.CreateAddress .city3').show();
                    $('.CreateAddress .city3').val(city1);
                }
                if(value1 == 'فارس'){
                    $('.CreateAddress .city4').show();
                    $('.CreateAddress .city4').val(city1);
                }
                if(value1 == 'خوزستان'){
                    $('.CreateAddress .city5').show();
                    $('.CreateAddress .city5').val(city1);
                }
                if(value1 == 'آذربایجان شرقی'){
                    $('.CreateAddress .city6').show();
                    $('.CreateAddress .city6').val(city1);
                }
                if(value1 == 'مازندران'){
                    $('.CreateAddress .city7').show();
                    $('.CreateAddress .city7').val(city1);
                }
                if(value1 == 'آذربایجان غربی'){
                    $('.CreateAddress .city8').show();
                    $('.CreateAddress .city8').val(city1);
                }
                if(value1 == 'کرمان'){
                    $('.CreateAddress .city9').show();
                    $('.CreateAddress .city9').val(city1);
                }
                if(value1 == 'سیستان و بلوچستان'){
                    $('.CreateAddress .city10').show();
                    $('.CreateAddress .city10').val(city1);
                }
                if(value1 == 'البرز'){
                    $('.CreateAddress .city11').show();
                    $('.CreateAddress .city11').val(city1);
                }
                if(value1 == 'گیلان'){
                    $('.CreateAddress .city12').show();
                    $('.CreateAddress .city12').val(city1);
                }
                if(value1 == 'کرمانشاه'){
                    $('.CreateAddress .city13').show();
                    $('.CreateAddress .city13').val(city1);
                }
                if(value1 == 'گلستان'){
                    $('.CreateAddress .city14').show();
                    $('.CreateAddress .city14').val(city1);
                }
                if(value1 == 'هرمزگان'){
                    $('.CreateAddress .city15').show();
                    $('.CreateAddress .city15').val(city1);
                }
                if(value1 == 'لرستان'){
                    $('.CreateAddress .city16').show();
                    $('.CreateAddress .city16').val(city1);
                }
                if(value1 == 'همدان'){
                    $('.CreateAddress .city17').show();
                    $('.CreateAddress .city17').val(city1);
                }
                if(value1 == 'کردستان'){
                    $('.CreateAddress .city18').show();
                    $('.CreateAddress .city18').val(city1);
                }
                if(value1 == 'مرکزی'){
                    $('.CreateAddress .city19').show();
                    $('.CreateAddress .city19').val(city1);
                }
                if(value1 == 'قم'){
                    $('.CreateAddress .city20').show();
                    $('.CreateAddress .city20').val(city1);
                }
                if(value1 == 'قزوین'){
                    $('.CreateAddress .city21').show();
                    $('.CreateAddress .city21').val(city1);
                }
                if(value1 == 'اردبیل'){
                    $('.CreateAddress .city22').show();
                    $('.CreateAddress .city22').val(city1);
                }
                if(value1 == 'بوشهر'){
                    $('.CreateAddress .city23').show();
                    $('.CreateAddress .city23').val(city1);
                }
                if(value1 == 'یزد'){
                    $('.CreateAddress .city24').show();
                    $('.CreateAddress .city24').val(city1);
                }
                if(value1 == 'زنجان'){
                    $('.CreateAddress .city25').show();
                    $('.CreateAddress .city25').val(city1);
                }
                if(value1 == 'چهارمحال و بختیاری'){
                    $('.CreateAddress .city26').show();
                    $('.CreateAddress .city26').val(city1);
                }
                if(value1 == 'خراسان شمالی'){
                    $('.CreateAddress .city27').show();
                    $('.CreateAddress .city27').val(city1);
                }
                if(value1 == 'خراسان جنوبی'){
                    $('.CreateAddress .city28').show();
                    $('.CreateAddress .city28').val(city1);
                }
                if(value1 == 'کهگیلویه و بویراحمد'){
                    $('.CreateAddress .city29').show();
                    $('.CreateAddress .city29').val(city1);
                }
                if(value1 == 'سمنان'){
                    $('.CreateAddress .city30').show();
                    $('.CreateAddress .city30').val(city1);
                }
                if(value1 == 'ایلام'){
                    $('.CreateAddress .city31').show();
                    $('.CreateAddress .city31').val(city1);
                }
            }
            function makePrice(price){
                price += '';
                x = price.split('.');
                let x1 = x[0];
                let x2 = x.length > 1 ? '.' + x[1] : '';
                let rgx = /(\d+)(\d{3})/;
                while (rgx.test(x1)) {
                    x1 = x1.replace(rgx, '$1' + ',' + '$2');
                }
                let fPrice = x1 + x2;
                return (price>=1 ? fPrice : 0);
            }
        })
    </script>
@endsection


@section('map')
    <script type="text/javascript" src="https://cdn.map.ir/web-sdk/1.4.2/js/mapp.env.js"></script>
    <script type="text/javascript" src="https://cdn.map.ir/web-sdk/1.4.2/js/mapp.min.js"></script>
@endsection
@section('mapLink')
    <link rel="stylesheet" href="https://cdn.map.ir/web-sdk/1.4.2/css/mapp.min.css">
    <link rel="stylesheet" href="https://cdn.map.ir/web-sdk/1.4.2/css/fa/style.css">
@endsection

