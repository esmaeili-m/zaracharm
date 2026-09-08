<!DOCTYPE html>
<html data-header-styles="light" data-menu-styles="dark" data-nav-layout="vertical" data-theme-mode="light"
      data-toggled="close" data-width="fullwidth" lang="fa">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <meta content="IE=edge" http-equiv="X-UA-Compatible"/>
    <meta content="پنل مدیریت {{env('APP_NAME','استارت وب وان')}}" name="Description"/>
    <meta content="پنل مدیریت {{env('APP_NAME','استارت وب وان')}}" name="Author"/>
    <meta content="پنل مدیریت {{env('APP_NAME','استارت وب وان')}}" name="keywords"/>
    <!-- Title -->
    <title>
        @yield('title','Dashboard Panel')
    </title>
    <!-- Favicon -->
    <link href="{{asset('dashboard/images/brand-logos/favicon.ico')}}" rel="icon" type="image/x-icon"/>
    <!-- Start::Styles -->
    <!-- Choices JS -->
    <script src="{{asset('dashboard')}}/libs/choices.js/public/assets/scripts/choices.min.js"></script>
    <!-- Main Theme Js -->
    <script src="{{asset('dashboard')}}/js/main.js"></script>
    <!-- Bootstrap Css -->
    <link href="{{asset('dashboard/libs/bootstrap/css/bootstrap.rtl.min.css')}}" id="style" rel="stylesheet"/>
    <!-- Style Css -->
    <link href="{{asset('dashboard')}}/css/styles.css" rel="stylesheet"/>
    <!-- Icons Css -->
    <link href="{{asset('dashboard')}}/css/icons.css" rel="stylesheet"/>
    <!-- Node Waves Css -->
    <link href="{{asset('dashboard')}}/libs/node-waves/waves.min.css" rel="stylesheet"/>
    <!-- Simplebar Css -->
    <link href="{{asset('dashboard')}}/libs/simplebar/simplebar.min.css" rel="stylesheet"/>
    <!-- Color Picker Css -->
    <link href="{{asset('dashboard')}}/libs/flatpickr/flatpickr.min.css" rel="stylesheet"/>
    <link href="{{asset('dashboard')}}/libs/%40simonwep/pickr/themes/nano.min.css" rel="stylesheet"/>
    <!-- Choices Css -->
    <link href="{{asset('dashboard')}}/libs/choices.js/public/assets/styles/choices.min.css" rel="stylesheet"/>
    <!-- FlatPickr CSS -->
    <link href="{{asset('dashboard')}}/libs/flatpickr/flatpickr.min.css" rel="stylesheet"/>
    <!-- Auto Complete CSS -->
    <link href="{{asset('dashboard')}}/libs/%40tarekraafat/autocomplete.js/css/autoComplete.css" rel="stylesheet"/>
    <!-- Date & Time Picker CSS -->
    <link href="{{asset('dashboard')}}/libs/flatpickr/flatpickr.min.css" rel="stylesheet"/>
    @stack('styles')
    <!-- End::Styles -->
    <style>
        .my-swal-popup {
            font-family: inherit !important;
            border-radius: 12px !important;
        }
    </style>
    <script>
        window.Laravel = {
            baseUrl: "{{ asset('') }}"
        };
    </script>
    @livewireStyles

</head>

<body>
<!-- Start::main-switcher -->
<div aria-labelledby="offcanvasRightLabel" class="offcanvas offcanvas-end" id="switcher-canvas" tabindex="-1">
    <div class="offcanvas-header border-bottom d-block p-0">
        <div class="d-flex align-items-center justify-content-between p-3">
            <h5 class="offcanvas-title text-default" id="offcanvasRightLabel">
                سوییچر
            </h5>
            <button aria-label="Close" class="btn-close" data-bs-dismiss="offcanvas" type="button">
            </button>
        </div>
        <nav class="border-top border-block-start-dashed">
            <div class="nav nav-tabs nav-justified" id="switcher-main-tab" role="tablist">
                <button aria-controls="switcher-home" aria-selected="true" class="nav-link active"
                        data-bs-target="#switcher-home" data-bs-toggle="tab" id="switcher-home-tab" role="tab"
                        type="button">
                    سبک های تم
                </button>
                <button aria-controls="switcher-profile" aria-selected="false" class="nav-link"
                        data-bs-target="#switcher-profile" data-bs-toggle="tab" id="switcher-profile-tab" role="tab"
                        type="button">
                    رنگ های تم
                </button>
            </div>
        </nav>
    </div>
    <div class="offcanvas-body">
        <div class="tab-content" id="nav-tabContent">
            <div aria-labelledby="switcher-home-tab" class="tab-pane fade show active border-0" id="switcher-home"
                 role="tabpanel" tabindex="0">
                <div class="">
                    <p class="switcher-style-head">
                        حالت رنگ تم:
                    </p>
                    <div class="row switcher-style gx-0">
                        <div class="col-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-light-theme">
                                    روشن
                                </label>
                                <input checked="" class="form-check-input" id="switcher-light-theme" name="theme-style"
                                       type="radio"/>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-dark-theme">
                                    تاریک
                                </label>
                                <input class="form-check-input" id="switcher-dark-theme" name="theme-style"
                                       type="radio"/>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="">
                    <p class="switcher-style-head">
                        جهت:
                    </p>
                    <div class="row switcher-style gx-0">
                        <div class="col-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-ltr">
                                    چپ به راست
                                </label>
                                <input checked="" class="form-check-input" id="switcher-ltr" name="direction"
                                       type="radio"/>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-rtl">
                                    راست به چپ
                                </label>
                                <input class="form-check-input" id="switcher-rtl" name="direction" type="radio"/>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="">
                    <p class="switcher-style-head">
                        سبک های ناوبری:
                    </p>
                    <div class="row switcher-style gx-0">
                        <div class="col-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-vertical">
                                    عمودی
                                </label>
                                <input checked="" class="form-check-input" id="switcher-vertical"
                                       name="navigation-style" type="radio"/>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-horizontal">
                                    افقی
                                </label>
                                <input class="form-check-input" id="switcher-horizontal" name="navigation-style"
                                       type="radio"/>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="navigation-menu-styles">
                    <p class="switcher-style-head">
                        سبک های منوی عمودی و افقی:
                    </p>
                    <div class="row switcher-style gx-0 pb-2 gy-2">
                        <div class="col-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-menu-click">
                                    منو کلیک کنید
                                </label>
                                <input class="form-check-input" id="switcher-menu-click" name="navigation-menu-styles"
                                       type="radio"/>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-menu-hover">
                                    منو شناور
                                </label>
                                <input class="form-check-input" id="switcher-menu-hover" name="navigation-menu-styles"
                                       type="radio"/>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-icon-click">
                                    نماد کلیک کنید
                                </label>
                                <input class="form-check-input" id="switcher-icon-click" name="navigation-menu-styles"
                                       type="radio"/>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-icon-hover">
                                    نماد شناور
                                </label>
                                <input class="form-check-input" id="switcher-icon-hover" name="navigation-menu-styles"
                                       type="radio"/>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="sidemenu-layout-styles">
                    <p class="switcher-style-head">
                        سبک های چیدمان منوی جانبی:
                    </p>
                    <div class="row switcher-style gx-0 pb-2 gy-2">
                        <div class="col-sm-6">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-default-menu">
                                    منوی پیش فرض
                                </label>
                                <input checked="" class="form-check-input" id="switcher-default-menu"
                                       name="sidemenu-layout-styles" type="radio"/>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-closed-menu">
                                    منوی بسته
                                </label>
                                <input class="form-check-input" id="switcher-closed-menu" name="sidemenu-layout-styles"
                                       type="radio"/>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-icontext-menu">
                                    متن نماد
                                </label>
                                <input class="form-check-input" id="switcher-icontext-menu"
                                       name="sidemenu-layout-styles" type="radio"/>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-icon-overlay">
                                    مدرن نماد
                                </label>
                                <input class="form-check-input" id="switcher-icon-overlay" name="sidemenu-layout-styles"
                                       type="radio"/>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-detached">
                                    جدا شده
                                </label>
                                <input class="form-check-input" id="switcher-detached" name="sidemenu-layout-styles"
                                       type="radio"/>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-double-menu">
                                    منوی دوتایی
                                </label>
                                <input class="form-check-input" id="switcher-double-menu" name="sidemenu-layout-styles"
                                       type="radio"/>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="">
                    <p class="switcher-style-head">
                        سبک های صفحه:
                    </p>
                    <div class="row switcher-style gx-0">
                        <div class="col-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-regular">
                                    منظم
                                </label>
                                <input checked="" class="form-check-input" id="switcher-regular" name="page-styles"
                                       type="radio"/>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-classic">
                                    کلاسیک
                                </label>
                                <input class="form-check-input" id="switcher-classic" name="page-styles" type="radio"/>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-modern">
                                    مدرن
                                </label>
                                <input class="form-check-input" id="switcher-modern" name="page-styles" type="radio"/>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="">
                    <p class="switcher-style-head">
                        سبک های عرض چیدمان:
                    </p>
                    <div class="row switcher-style gx-0">
                        <div class="col-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-default-width">
                                    پیش فرض
                                </label>
                                <input class="form-check-input" id="switcher-default-width" name="layout-width"
                                       type="radio"/>
                            </div>
                        </div>
                        <div class="col-5">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-full-width">
                                    عرض کامل
                                </label>
                                <input checked="" class="form-check-input" id="switcher-full-width" name="layout-width"
                                       type="radio"/>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-boxed">
                                    جعبه دار
                                </label>
                                <input class="form-check-input" id="switcher-boxed" name="layout-width" type="radio"/>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="">
                    <p class="switcher-style-head">
                        موقعیت های منو:
                    </p>
                    <div class="row switcher-style gx-0">
                        <div class="col-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-menu-fixed">
                                    ثابت شد
                                </label>
                                <input checked="" class="form-check-input" id="switcher-menu-fixed"
                                       name="menu-positions" type="radio"/>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-menu-scroll">
                                    قابل پیمایش
                                </label>
                                <input class="form-check-input" id="switcher-menu-scroll" name="menu-positions"
                                       type="radio"/>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="">
                    <p class="switcher-style-head">
                        موقعیت های هدر:
                    </p>
                    <div class="row switcher-style gx-0">
                        <div class="col-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-header-fixed">
                                    ثابت شد
                                </label>
                                <input checked="" class="form-check-input" id="switcher-header-fixed"
                                       name="header-positions" type="radio"/>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-header-scroll">
                                    قابل پیمایش
                                </label>
                                <input class="form-check-input" id="switcher-header-scroll" name="header-positions"
                                       type="radio"/>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="">
                    <p class="switcher-style-head">
                        لودر:
                    </p>
                    <div class="row switcher-style gx-0">
                        <div class="col-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-loader-enable">
                                    فعال کردن
                                </label>
                                <input class="form-check-input" id="switcher-loader-enable" name="page-loader"
                                       type="radio"/>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-loader-disable">
                                    غیر فعال کردن
                                </label>
                                <input checked="" class="form-check-input" id="switcher-loader-disable"
                                       name="page-loader" type="radio"/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div aria-labelledby="switcher-profile-tab" class="tab-pane fade border-0" id="switcher-profile"
                 role="tabpanel" tabindex="0">
                <div>
                    <div class="theme-colors">
                        <p class="switcher-style-head">
                            رنگ های منو:
                        </p>
                        <div class="d-flex switcher-style pb-2">
                            <div class="form-check switch-select me-3">
                                <input class="form-check-input color-input color-white" data-bs-placement="top"
                                       data-bs-toggle="tooltip" id="switcher-menu-light" name="menu-colors"
                                       title="منوی روشن" type="radio"/>
                            </div>
                            <div class="form-check switch-select me-3">
                                <input checked="" class="form-check-input color-input color-dark"
                                       data-bs-placement="top" data-bs-toggle="tooltip" id="switcher-menu-dark"
                                       name="menu-colors" title="منوی تاریک" type="radio"/>
                            </div>
                            <div class="form-check switch-select me-3">
                                <input class="form-check-input color-input color-primary" data-bs-placement="top"
                                       data-bs-toggle="tooltip" id="switcher-menu-primary" name="menu-colors"
                                       title="منوی رنگ" type="radio"/>
                            </div>
                            <div class="form-check switch-select me-3">
                                <input class="form-check-input color-input color-gradient" data-bs-placement="top"
                                       data-bs-toggle="tooltip" id="switcher-menu-gradient" name="menu-colors"
                                       title="منوی گرادیان" type="radio"/>
                            </div>
                            <div class="form-check switch-select me-3">
                                <input class="form-check-input color-input color-transparent" data-bs-placement="top"
                                       data-bs-toggle="tooltip" id="switcher-menu-transparent" name="menu-colors"
                                       title="منوی شفاف" type="radio"/>
                            </div>
                        </div>
                        <div class="px-4 pb-3 text-muted fs-11">
                            توجه: اگر می خواهید رنگ را تغییر دهید، منو به صورت پویا تغییر می کند
                            از زیر انتخابگر رنگ اصلی تم
                        </div>
                    </div>
                    <div class="theme-colors">
                        <p class="switcher-style-head">
                            رنگ های سرصفحه:
                        </p>
                        <div class="d-flex switcher-style pb-2">
                            <div class="form-check switch-select me-3">
                                <input checked="" class="form-check-input color-input color-white"
                                       data-bs-placement="top" data-bs-toggle="tooltip" id="switcher-header-light"
                                       name="header-colors" title="سربرگ روشن" type="radio"/>
                            </div>
                            <div class="form-check switch-select me-3">
                                <input class="form-check-input color-input color-dark" data-bs-placement="top"
                                       data-bs-toggle="tooltip" id="switcher-header-dark" name="header-colors"
                                       title="سربرگ تاریک" type="radio"/>
                            </div>
                            <div class="form-check switch-select me-3">
                                <input class="form-check-input color-input color-primary" data-bs-placement="top"
                                       data-bs-toggle="tooltip" id="switcher-header-primary" name="header-colors"
                                       title="سربرگ رنگ" type="radio"/>
                            </div>
                            <div class="form-check switch-select me-3">
                                <input class="form-check-input color-input color-gradient" data-bs-placement="top"
                                       data-bs-toggle="tooltip" id="switcher-header-gradient" name="header-colors"
                                       title="هدر گرادیان" type="radio"/>
                            </div>
                            <div class="form-check switch-select me-3">
                                <input class="form-check-input color-input color-transparent" data-bs-placement="top"
                                       data-bs-toggle="tooltip" id="switcher-header-transparent" name="header-colors"
                                       title="سربرگ شفاف" type="radio"/>
                            </div>
                        </div>
                        <div class="px-4 pb-3 text-muted fs-11">
                            توجه: اگر می خواهید رنگ هدر را به صورت پویا تغییر دهید
                            انتخابگر رنگ اصلی طرح زمینه را از زیر تغییر دهید
                        </div>
                    </div>
                    <div class="theme-colors">
                        <p class="switcher-style-head">
                            موضوع اصلی:
                        </p>
                        <div class="d-flex flex-wrap align-items-center switcher-style">
                            <div class="form-check switch-select me-3">
                                <input class="form-check-input color-input color-primary-1" id="switcher-primary"
                                       name="theme-primary" type="radio"/>
                            </div>
                            <div class="form-check switch-select me-3">
                                <input class="form-check-input color-input color-primary-2" id="switcher-primary1"
                                       name="theme-primary" type="radio"/>
                            </div>
                            <div class="form-check switch-select me-3">
                                <input class="form-check-input color-input color-primary-3" id="switcher-primary2"
                                       name="theme-primary" type="radio"/>
                            </div>
                            <div class="form-check switch-select me-3">
                                <input class="form-check-input color-input color-primary-4" id="switcher-primary3"
                                       name="theme-primary" type="radio"/>
                            </div>
                            <div class="form-check switch-select me-3">
                                <input class="form-check-input color-input color-primary-5" id="switcher-primary4"
                                       name="theme-primary" type="radio"/>
                            </div>
                            <div class="form-check switch-select ps-0 mt-1 color-primary-light">
                                <div class="theme-container-primary">
                                </div>
                                <div class="pickr-container-primary" onchange="updateChartColor(this.value)">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="theme-colors">
                        <p class="switcher-style-head">
                            پس زمینه تم:
                        </p>
                        <div class="d-flex flex-wrap align-items-center switcher-style">
                            <div class="form-check switch-select me-3">
                                <input class="form-check-input color-input color-bg-1" id="switcher-background"
                                       name="theme-background" type="radio"/>
                            </div>
                            <div class="form-check switch-select me-3">
                                <input class="form-check-input color-input color-bg-2" id="switcher-background1"
                                       name="theme-background" type="radio"/>
                            </div>
                            <div class="form-check switch-select me-3">
                                <input class="form-check-input color-input color-bg-3" id="switcher-background2"
                                       name="theme-background" type="radio"/>
                            </div>
                            <div class="form-check switch-select me-3">
                                <input class="form-check-input color-input color-bg-4" id="switcher-background3"
                                       name="theme-background" type="radio"/>
                            </div>
                            <div class="form-check switch-select me-3">
                                <input class="form-check-input color-input color-bg-5" id="switcher-background4"
                                       name="theme-background" type="radio"/>
                            </div>
                            <div class="form-check switch-select ps-0 mt-1 tooltip-static-demo color-bg-transparent">
                                <div class="theme-container-background">
                                </div>
                                <div class="pickr-container-background">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="menu-image mb-3">
                        <p class="switcher-style-head">
                            منو با تصویر پس زمینه:
                        </p>
                        <div class="d-flex flex-wrap align-items-center switcher-style">
                            <div class="form-check switch-select menu-img-select m-2">
                                <input class="form-check-input bgimage-input bg-img1" id="switcher-bg-img"
                                       name="menu-background" type="radio"/>
                                <div class="bg-img-container">
                                    <img alt="" src="{{asset('dashboard')}}/images/menu-bg-images/bg-img1.jpg"/>
                                </div>
                            </div>
                            <div class="form-check switch-select menu-img-select m-2">
                                <input class="form-check-input bgimage-input bg-img2" id="switcher-bg-img1"
                                       name="menu-background" type="radio"/>
                                <div class="bg-img-container">
                                    <img alt="" src="{{asset('dashboard')}}/images/menu-bg-images/bg-img2.jpg"/>
                                </div>
                            </div>
                            <div class="form-check switch-select menu-img-select m-2">
                                <input class="form-check-input bgimage-input bg-img3" id="switcher-bg-img2"
                                       name="menu-background" type="radio"/>
                                <div class="bg-img-container">
                                    <img alt="" src="{{asset('dashboard')}}/images/menu-bg-images/bg-img3.jpg"/>
                                </div>
                            </div>
                            <div class="form-check switch-select menu-img-select m-2">
                                <input class="form-check-input bgimage-input bg-img4" id="switcher-bg-img3"
                                       name="menu-background" type="radio"/>
                                <div class="bg-img-container">
                                    <img alt="" src="{{asset('dashboard')}}/images/menu-bg-images/bg-img4.jpg"/>
                                </div>
                            </div>
                            <div class="form-check switch-select menu-img-select m-2">
                                <input class="form-check-input bgimage-input bg-img5" id="switcher-bg-img4"
                                       name="menu-background" type="radio"/>
                                <div class="bg-img-container">
                                    <img alt="" src="{{asset('dashboard')}}/images/menu-bg-images/bg-img5.jpg"/>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-between canvas-footer">
                <a class="btn btn-primary" href="https://www.rtl-theme.com/author/hogondesign/products/"
                   target="_blank">
                    اکنون بخرید
                </a>
                <a class="btn btn-secondary" href="https://www.rtl-theme.com/author/hogondesign/products/"
                   target="_blank">
                    نمونه کارها ما
                </a>
                <a class="btn btn-danger" href="javascript:void(0);" id="reset-all">
                    بازنشانی کنید
                </a>
            </div>
        </div>
    </div>
</div>
<!-- End::main-switcher -->
<!-- Loader -->
{{--<div id="loader">--}}
{{--    <img alt="" src="{{asset('dashboard')}}/images/storage/loader.svg"/>--}}
{{--</div>--}}
<!-- Loader -->
<div class="page">
    <!-- Start::main-header -->
    <header class="app-header sticky" id="header">
        <div class="main-header-container container-fluid px-0">
            <!-- Start::header-content-left -->
            <div class="header-content-left">
                <!-- Start::header-element -->
                <div class="header-element">
                    <div class="horizontal-logo">
                        <a class="header-logo" href="/">
                            @php($logo=\App\Models\Setting::where('key','logo')->with('media')->first())

                            <img alt="لوگو" class="desktop-logo"
                                 src=""/>
                            <img alt="لوگو" class="toggle-logo"
                                 src="{{asset('dashboard')}}/images/brand-logos/toggle-logo.png"/>
                            <img alt="لوگو" class="desktop-dark"
                                 src="{{asset('dashboard')}}/images/brand-logos/desktop-dark.png"/>
                            <img alt="لوگو" class="toggle-dark"
                                 src="{{asset('dashboard')}}/images/brand-logos/toggle-dark.png"/>
                        </a>
                    </div>
                </div>
                <!-- End::header-element -->
                <!-- Start::header-element -->
                <div class="header-element mx-lg-0 mx-2">
                    <a aria-label="Hide Sidebar"
                       class="sidemenu-toggle header-link animated-arrow hor-toggle horizontal-navtoggle"
                       data-bs-toggle="sidebar" href="javascript:void(0);">
        <span>
        </span>
                    </a>
                </div>
                <!-- End::header-element -->
                <!-- Start::header-element -->

                <!-- End::header-element -->
            </div>
            <!-- End::header-content-left -->
            <!-- Start::header-content-right -->
            <ul class="header-content-right">
                <!-- Start::header-element -->
                <li class="header-element d-md-none d-block">
                    <a class="header-link" data-bs-target="#header-responsive-search" data-bs-toggle="modal"
                       href="javascript:void(0);">
                        <!-- Start::header-link-icon -->
                        <i class="bi bi-search header-link-icon">
                        </i>
                        <!-- End::header-link-icon -->
                    </a>
                </li>


                <li class="header-element dropdown">
                    <!-- Start::header-link|dropdown-toggle -->
                    <a aria-expanded="false" class="header-link dropdown-toggle" data-bs-auto-close="outside"
                       data-bs-toggle="dropdown" href="javascript:void(0);" id="mainHeaderProfile">
                        <div class="d-flex align-items-center">
                            <div>
                                <img alt="img" class="avatar avatar-xs"
                                     src="{{ auth()->user()->avatar ? auth()->user()->avatarUrl : asset('dashboard/images/faces/14.jpg')}}"/>
                            </div>
                        </div>
                    </a>
                    <!-- End::header-link|dropdown-toggle -->
                    <ul aria-labelledby="mainHeaderProfile"
                        class="main-header-dropdown dropdown-menu pt-0 overflow-hidden header-profile-dropdown dropdown-menu-end">
                        <li>
                            <a class="dropdown-item d-flex align-items-center"
                               href="#"
                               onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                <i class="ti ti-logout me-2 fs-16"></i>
                                از سیستم خارج شوید
                            </a>

                            <form id="logout-form"
                                  action="{{ route('logout') }}"
                                  method="POST"
                                  class="d-none">
                                @csrf
                            </form>
                        </li>
                    </ul>
                </li>
                <!-- End::header-element -->
                <!-- Start::header-element -->
                <li class="header-element">
                    <!-- Start::header-link|switcher-icon -->
                    <a class="header-link switcher-icon" data-bs-target="#switcher-canvas" data-bs-toggle="offcanvas"
                       href="javascript:void(0);">
                        <svg class="header-link-icon" height="1em" viewbox="0 0 24 24" width="1em"
                             xmlns="http://www.w3.org/2000/svg">
                            <g color="currentColor" fill="none" stroke="currentColor" stroke-linecap="round"
                               stroke-linejoin="round" stroke-width="1.5">
                                <path
                                    d="m21.318 7.141l-.494-.856c-.373-.648-.56-.972-.878-1.101c-.317-.13-.676-.027-1.395.176l-1.22.344c-.459.106-.94.046-1.358-.17l-.337-.194a2 2 0 0 1-.788-.967l-.334-.998c-.22-.66-.33-.99-.591-1.178c-.261-.19-.609-.19-1.303-.19h-1.115c-.694 0-1.041 0-1.303.19c-.261.188-.37.518-.59 1.178l-.334.998a2 2 0 0 1-.789.967l-.337.195c-.418.215-.9.275-1.358.17l-1.22-.345c-.719-.203-1.078-.305-1.395-.176c-.318.129-.505.453-.878 1.1l-.493.857c-.35.608-.525.911-.491 1.234c.034.324.268.584.736 1.105l1.031 1.153c.252.319.431.875.431 1.375s-.179 1.056-.43 1.375l-1.032 1.152c-.468.521-.702.782-.736 1.105s.14.627.49 1.234l.494.857c.373.647.56.971.878 1.1s.676.028 1.395-.176l1.22-.344a2 2 0 0 1 1.359.17l.336.194c.36.23.636.57.788.968l.334.997c.22.66.33.99.591 1.18c.262.188.609.188 1.303.188h1.115c.694 0 1.042 0 1.303-.189s.371-.519.59-1.179l.335-.997c.152-.399.428-.738.788-.968l.336-.194c.42-.215.9-.276 1.36-.17l1.22.344c.718.204 1.077.306 1.394.177c.318-.13.505-.454.878-1.101l.493-.857c.35-.607.525-.91.491-1.234s-.268-.584-.736-1.105l-1.031-1.152c-.252-.32-.431-.875-.431-1.375s.179-1.056.43-1.375l1.032-1.153c.468-.52.702-.781.736-1.105s-.14-.626-.49-1.234">
                                </path>
                                <path d="M15.52 12a3.5 3.5 0 1 1-7 0a3.5 3.5 0 0 1 7 0">
                                </path>
                            </g>
                        </svg>
                    </a>
                    <!-- End::header-link|switcher-icon -->
                </li>
                <!-- End::header-element -->
            </ul>
            <!-- End::header-content-right -->
        </div>
    </header>


    <aside class="app-sidebar sticky" id="sidebar">
        <!-- Start::main-sidebar-header -->
        <div class="main-sidebar-header">
            <a class="header-logo" href="/">

                <img alt="لوگو" width="80px" height="100px" style="height: 50px !important; width: 60px !important;" class="desktop-logo" src="{{asset('storage/'.$logo?->media->first()?->file_path)}}"/>
                <img alt="لوگو" width="80px" height="100px" style="height: 50px !important; width: 60px !important;" class="toggle-dark" src="{{asset('storage/'.$logo?->media?->first()?->file_path)}}"/>
                <img alt="لوگو" width="80px" height="100px" style="height: 50px !important; width: 60px !important;" class="desktop-dark" src="{{asset('storage/'.$logo?->media?->first()?->file_path)}}"/>
                <img alt="لوگو" width="80px" height="100px" style="height: 50px !important; width: 60px !important;" class="toggle-logo" src="{{asset('storage/'.$logo?->media?->first()?->file_path)}}"/>
            </a>
        </div>
        <!-- End::main-sidebar-header -->
        <!-- Start::main-sidebar -->
        <div class="main-sidebar" id="sidebar-scroll">
            <!-- Start::nav -->
            <nav class="main-menu-container nav nav-pills flex-column sub-open">

                {{-- Sidebar Left --}}
                <div class="slide-left" id="slide-left">
                    <svg fill="#7b8191"
                         height="24"
                         viewBox="0 0 24 24"
                         width="24"
                         xmlns="http://www.w3.org/2000/svg">
                        <path d="M13.293 6.293 7.586 12l5.707 5.707 1.414-1.414L10.414 12l4.293-4.293z"/>
                    </svg>
                </div>

                <ul class="main-menu">

                    {{-- ========================================================= --}}
                    {{-- داشبورد --}}
                    {{-- ========================================================= --}}

                    <li class="slide">
                        <a class="side-menu__item {{ request()->routeIs('dashboard.*') ? 'active' : '' }}"
                           href="{{ route('dashboard') }}">

                            <i class="ri-dashboard-3-line side-menu__icon"></i>

                            <span class="side-menu__label">
                        داشبورد
                    </span>

                        </a>
                    </li>


                    {{-- ========================================================= --}}
                    {{-- کاربران --}}
                    {{-- ========================================================= --}}

                    <li class="slide__category">
                <span class="category-name">
                    کاربران
                </span>
                    </li>

                    <li class="slide has-sub {{ request()->routeIs('users.*') || request()->routeIs('roles.*') ? 'open active' : '' }}">

                        <a class="side-menu__item" href="javascript:void(0);">

                            <i class="ri-team-line side-menu__icon"></i>

                            <span class="side-menu__label">
                        مدیریت کاربران
                    </span>

                            <i class="ri-arrow-down-s-line side-menu__angle"></i>

                        </a>

                        <ul class="slide-menu child1">

                            <li class="slide side-menu__label1">
                                <a href="javascript:void(0)">
                                    مدیریت کاربران
                                </a>
                            </li>

                            {{-- کاربران --}}
                            <li class="slide">

                                <a class="side-menu__item {{ request()->routeIs('users.*') ? 'active' : '' }}"
                                   href="{{ route('users.index') }}">

                                    <i class="ri-user-3-line side-menu__icon"></i>

                                    <span>
                                کاربران
                            </span>

                                </a>

                            </li>

                            {{-- نقش ها --}}
                            <li class="slide">

                                <a class="side-menu__item {{ request()->routeIs('roles.*') ? 'active' : '' }}"
                                   href="{{ route('roles.index') }}">

                                    <i class="ri-shield-user-line side-menu__icon"></i>

                                    <span>
                                نقش‌ها
                            </span>

                                </a>

                            </li>

                        </ul>

                    </li>


                    {{-- ========================================================= --}}
                    {{-- محتوا --}}
                    {{-- ========================================================= --}}

                    <li class="slide__category">
                <span class="category-name">
                    محتوا
                </span>
                    </li>


                    {{-- صفحات --}}
                    <li class="slide">

                        <a class="side-menu__item {{ request()->routeIs('pages.*') ? 'active' : '' }}"
                           href="{{ route('pages.index') }}">

                            <i class="ri-pages-line side-menu__icon"></i>

                            <span class="side-menu__label">
                        صفحات
                    </span>

                        </a>

                    </li>


                    {{-- مقالات --}}
                    <li class="slide">

                        <a class="side-menu__item {{ request()->routeIs('articles.*') ? 'active' : '' }}"
                           href="{{ route('articles.index') }}">

                            <i class="ri-article-line side-menu__icon"></i>

                            <span class="side-menu__label">
                        مقالات
                    </span>

                        </a>

                    </li>


                    {{-- استوری --}}
                    <li class="slide">

                        <a class="side-menu__item {{ request()->routeIs('stories.*') ? 'active' : '' }}"
                           href="{{ route('stories.index') }}">

                            <i class="ri-instagram-line side-menu__icon"></i>

                            <span class="side-menu__label">
                        استوری‌ها
                    </span>

                        </a>

                    </li>


                    {{-- گالری --}}



                    {{-- سوالات متداول --}}
                    <li class="slide">

                        <a class="side-menu__item {{ request()->routeIs('faq.*') ? 'active' : '' }}"
                           href="{{ route('faq.index') }}">

                            <i class="ri-question-answer-line side-menu__icon"></i>

                            <span class="side-menu__label">
                        سوالات متداول
                    </span>

                        </a>

                    </li>


                    {{-- منوهای سایت --}}
                    <li class="slide">

                        <a class="side-menu__item {{ request()->routeIs('menus.*') ? 'active' : '' }}"
                           href="{{ route('menus.index') }}">

                            <i class="ri-menu-2-line side-menu__icon"></i>

                            <span class="side-menu__label">
                        منوهای سایت
                    </span>

                        </a>

                    </li>


                    {{-- ========================================================= --}}
                    {{-- فروشگاه --}}
                    {{-- ========================================================= --}}

                    <li class="slide__category">
                <span class="category-name">
                    فروشگاه
                </span>
                    </li>


                    {{-- محصولات --}}
                    <li class="slide">

                        <a class="side-menu__item {{ request()->routeIs('products.*') ? 'active' : '' }}"
                           href="{{ route('products.index') }}">

                            <i class="ri-shopping-bag-3-line side-menu__icon"></i>

                            <span class="side-menu__label">
                        محصولات
                    </span>

                        </a>

                    </li>


                    {{-- دسته بندی --}}
                    <li class="slide">

                        <a class="side-menu__item {{ request()->routeIs('categories.*') ? 'active' : '' }}"
                           href="{{ route('categories.index') }}">

                            <i class="ri-folder-3-line side-menu__icon"></i>

                            <span class="side-menu__label">
                        دسته‌بندی‌ها
                    </span>

                        </a>

                    </li>


                    {{-- برندها --}}
                    <li class="slide">

                        <a class="side-menu__item {{ request()->routeIs('brands.*') ? 'active' : '' }}"
                           href="{{ route('brands.index') }}">

                            <i class="ri-price-tag-3-line side-menu__icon"></i>

                            <span class="side-menu__label">
                        برندها
                    </span>

                        </a>

                    </li>


                    {{-- ویژگی ها --}}
                    <li class="slide">

                        <a class="side-menu__item {{ request()->routeIs('options.*') ? 'active' : '' }}"
                           href="{{ route('options.index') }}">

                            <i class="ri-list-settings-line side-menu__icon"></i>

                            <span class="side-menu__label">
                        ویژگی‌ها
                    </span>

                        </a>

                    </li>


                    {{-- مشخصات فنی --}}
                    <li class="slide">

                        <a class="side-menu__item {{ request()->routeIs('specifications.*') ? 'active' : '' }}"
                           href="{{ route('specifications.index') }}">

                            <i class="ri-file-list-3-line side-menu__icon"></i>

                            <span class="side-menu__label">
                        مشخصات فنی
                    </span>

                        </a>

                    </li>


                    {{-- انبار --}}
                    <li class="slide">

                        <a class="side-menu__item {{ request()->routeIs('inventories.*') ? 'active' : '' }}"
                           href="{{ route('inventories.index') }}">

                            <i class="ri-archive-stack-line side-menu__icon"></i>

                            <span class="side-menu__label">
                        انبارداری
                    </span>

                        </a>

                    </li>


                    {{-- ========================================================= --}}
                    {{-- بازاریابی --}}
                    {{-- ========================================================= --}}

                    <li class="slide__category">
                <span class="category-name">
                    بازاریابی
                </span>
                    </li>


                    {{-- تخفیف ها --}}
                    <li class="slide">

                        <a class="side-menu__item {{ request()->routeIs('discounts.*') ? 'active' : '' }}"
                           href="{{ route('discounts.index') }}">

                            <i class="ri-discount-percent-line side-menu__icon"></i>

                            <span class="side-menu__label">
                        تخفیف‌ها
                    </span>

                        </a>

                    </li>


                    {{-- کوپن --}}
                    <li class="slide">

                        <a class="side-menu__item {{ request()->routeIs('coupons.*') ? 'active' : '' }}"
                           href="{{ route('coupons.index') }}">

                            <i class="ri-coupon-3-line side-menu__icon"></i>

                            <span class="side-menu__label">
                        کوپن‌های تخفیف
                    </span>

                        </a>

                    </li>


                    {{-- کمپین --}}
                    <li class="slide">

                        <a class="side-menu__item {{ request()->routeIs('campaign.*') ? 'active' : '' }}"
                           href="{{ route('campaign.index') }}">

                            <i class="ri-megaphone-line side-menu__icon"></i>

                            <span class="side-menu__label">
                        کمپین‌ها
                    </span>

                        </a>

                    </li>


                    {{-- اسلایدر --}}
                    <li class="slide">

                        <a class="side-menu__item {{ request()->routeIs('sliders.*') ? 'active' : '' }}"
                           href="{{ route('sliders.index') }}">

                            <i class="ri-slideshow-3-line side-menu__icon"></i>

                            <span class="side-menu__label">
                        اسلایدرها
                    </span>

                        </a>

                    </li>


                    {{-- ========================================================= --}}
                    {{-- ارتباطات --}}
                    {{-- ========================================================= --}}

                    <li class="slide__category">
                <span class="category-name">
                    ارتباطات
                </span>
                    </li>


                    {{-- پیام ها --}}
                    <li class="slide">

                        <a class="side-menu__item {{ request()->routeIs('messages.*') ? 'active' : '' }}"
                           href="{{ route('messages.index') }}">

                            <i class="ri-mail-line side-menu__icon"></i>

                            <span class="side-menu__label">
                        پیام‌ها
                    </span>

                        </a>

                    </li>


                    {{-- دیدگاه ها --}}
                    <li class="slide">

                        <a class="side-menu__item {{ request()->routeIs('comments.*') ? 'active' : '' }}"
                           href="{{ route('comments.index') }}">

                            <i class="ri-chat-3-line side-menu__icon"></i>

                            <span class="side-menu__label">
                        دیدگاه‌ها
                    </span>

                        </a>

                    </li>


                    {{-- تیکت --}}
                    <li class="slide">

                        <a class="side-menu__item {{ request()->routeIs('tickets.*') ? 'active' : '' }}"
                           href="{{ route('tickets.index') }}">

                            <i class="ri-customer-service-2-line side-menu__icon"></i>

                            <span class="side-menu__label">
                        تیکت‌ها و پشتیبانی
                    </span>

                        </a>

                    </li>


                    {{-- ========================================================= --}}
                    {{-- مالی --}}
                    {{-- ========================================================= --}}

                    <li class="slide__category">
                <span class="category-name">
                    مالی
                </span>
                    </li>


                    {{-- فاکتورها --}}
                    <li class="slide">

                        <a class="side-menu__item {{ request()->routeIs('invoices.*') ? 'active' : '' }}"
                           href="{{ route('invoices.index') }}">

                            <i class="ri-bill-line side-menu__icon"></i>

                            <span class="side-menu__label">
                        فاکتورها
                    </span>

                        </a>

                    </li>


                    {{-- ========================================================= --}}
                    {{-- سئو --}}
                    {{-- ========================================================= --}}

                    <li class="slide__category">
                <span class="category-name">
                    سئو
                </span>
                    </li>


                    {{-- مدیریت سئو --}}
                    <li class="slide">

                        <a class="side-menu__item {{ request()->routeIs('seo.*') ? 'active' : '' }}"
                           href="{{ route('seo.index') }}">

                            <i class="ri-seo-line side-menu__icon"></i>

                            <span class="side-menu__label">
                        مدیریت سئو
                    </span>

                        </a>

                    </li>


                    {{-- تگ ها --}}
                    <li class="slide">

                        <a class="side-menu__item {{ request()->routeIs('tags.*') ? 'active' : '' }}"
                           href="{{ route('tags.index') }}">

                            <i class="ri-price-tag-2-line side-menu__icon"></i>

                            <span class="side-menu__label">
                        تگ‌ها
                    </span>

                        </a>

                    </li>


                    {{-- ========================================================= --}}
                    {{-- سیستم --}}
                    {{-- ========================================================= --}}

                    <li class="slide__category">
                <span class="category-name">
                    سیستم
                </span>
                    </li>





                    {{-- تنظیمات --}}
                    <li class="slide">

                        <a class="side-menu__item {{ request()->routeIs('settings.*') ? 'active' : '' }}"
                           href="{{ route('settings.index') }}">

                            <i class="ri-settings-3-line side-menu__icon"></i>

                            <span class="side-menu__label">
                        تنظیمات
                    </span>

                        </a>

                    </li>

                </ul>


                {{-- Sidebar Right --}}
                <div class="slide-right" id="slide-right">

                    <svg fill="#7b8191"
                         height="24"
                         viewBox="0 0 24 24"
                         width="24"
                         xmlns="http://www.w3.org/2000/svg">

                        <path d="M10.707 17.707 16.414 12l-5.707-5.707-1.414 1.414L13.586 12l-4.293 4.293z"/>

                    </svg>

                </div>

            </nav>
            <!-- End::nav -->
        </div>        <!-- End::main-sidebar -->
    </aside>


    <div class="main-content app-content">
        <div class="container-fluid">
            {{$slot}}
        </div>
    </div>

    <footer class="footer mt-auto py-3 bg-white text-center">
        <div class="container">
     <span class="text-muted">
      حق چاپ ©
      <span id="year">
      </span>
      <a class="text-dark fw-medium" href="javascript:void(0);">
      </a>
      .
                        طراحی شده با
      <span class="bi bi-heart-fill text-danger">
      </span>
      توسط
      <a href="javascript:void(0);">
       <span class="fw-medium text-primary">
        STARTWEBONE
       </span>
      </a>
      همه
                        حقوق
                        رزرو شده است
     </span>
        </div>
    </footer>
</div>
<div class="scrollToTop">
   <span class="arrow lh-1">
    <i class="ti ti-caret-up fs-20">
    </i>
   </span>
</div>
<div id="responsive-overlay">
</div>
<script src="{{asset('dashboard')}}/libs/%40popperjs/core/umd/popper.min.js"></script>
<!-- Bootstrap JS -->
<script src="{{asset('dashboard')}}/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- Defaultmenu JS -->
<script src="{{asset('dashboard')}}/js/defaultmenu.min.js"></script>
<!-- Node Waves JS-->
<script src="{{asset('dashboard')}}/libs/node-waves/waves.min.js"></script>
<!-- Sticky JS -->
<script src="{{asset('dashboard')}}/js/sticky.js"></script>
<!-- Simplebar JS -->
<script src="{{asset('dashboard')}}/libs/simplebar/simplebar.min.js"></script>
<script src="{{asset('dashboard')}}/js/simplebar.js"></script>
<!-- Auto Complete JS -->
<script src="{{asset('dashboard')}}/libs/%40tarekraafat/autocomplete.js/autoComplete.min.js"></script>
<!-- Color Picker JS -->
<script src="{{asset('dashboard')}}/libs/%40simonwep/pickr/pickr.es5.min.js"></script>
<!-- Date & Time Picker JS -->
<script src="{{asset('dashboard')}}/libs/flatpickr/flatpickr.min.js"></script>

<script src="{{asset('dashboard')}}/js/sales-dashboard.js"></script>

<script src="{{asset('dashboard')}}/js/chat.js">
<!-- Custom JS -->
<script src="{{asset('dashboard')}}/js/custom.js"></script>
<!-- Custom-Switcher JS -->
<script src="{{asset('dashboard')}}/js/custom-switcher.min.js"></script>

<script src="{{asset('dashboard')}}/libs/quill/quill.min.js"></script>
<script src="{{asset('dashboard')}}/libs/sweetalert2/sweetalert2@11"></script>

@livewireScripts
@stack('scripts')
<script>
    document.addEventListener('livewire:init', () => {

        Livewire.on('alert', (event) => {

            Swal.fire({
                position: 'top-end',
                icon: event.type ?? 'success',
                title: event.title ?? '',
                text: event.text ?? '',

                showConfirmButton: false,
                customClass: {
                    popup: 'my-swal-popup'
                },
                timer: 2000,
                toast: true
            });

        });

    });
</script>
<script>
    document.addEventListener('livewire:initialized', () => {

        Livewire.on('close-modal', () => {

            document.querySelectorAll('.modal.show').forEach((modalEl) => {

                const modal = bootstrap.Modal.getInstance(modalEl);

                if (modal) modal.hide();

            });

        });

    });
</script>
</body>
</html>
