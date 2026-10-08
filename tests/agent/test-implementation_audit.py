"""Executable contract checks for the launch/onboarding hardening.

These checks deliberately do not pretend to be a WordPress runtime test. They
verify that the shipped integration points remain present in the source tree;
WordPress/PHP runtime checks still require a disposable WP install.
"""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def text(relative: str) -> str:
    return (ROOT / relative).read_text(encoding="utf-8")


def require(source: str, *needles: str) -> None:
    missing = [needle for needle in needles if needle not in source]
    assert not missing, f"missing contract strings: {missing}"


def test_onboarding_contract() -> None:
    source = text("flavor/inc/class-onboarding.php")
    require(
        source,
        "wp_ajax_flavor_save_wizard_draft",
        "public static function ajax_save_draft",
        "hero_image_url",
        "hero_image_id",
        "opening_hours",
        "_flavor_order_modes",
        "_flavor_city",
        "flavor_branch_hours",
        "FlavorCore\\Support\\Settings::update",
        "woocommerce_currency",
        "flavor_onboarding_product",
    )


def test_import_transfer_contract() -> None:
    importer = text("flavor/inc/class-demo-importer.php")
    transfer = text("flavor/inc/class-transfer.php")
    launch = text("flavor/inc/class-launch-center.php")
    require(
        importer,
        "flavor_demo_archive_",
        "private static function preview",
        "admin_post_flavor_demo_rollback",
        "private static function rollback_archive",
        "private static function set_state",
        "_flavor_demo_menu",
    )
    require(
        transfer,
        "flavor-settings-export",
        "admin_post_flavor_export_settings",
        "admin_post_flavor_import_settings",
        "check_admin_referer",
        "سفارش‌ها، مشتریان، محصولات، رسانه‌ها",
        "private static function excluded",
    )
    require(launch, "public static function checks", "order_modes", "opening_hours", "test_order")


def test_customizer_and_scope_contract() -> None:
    customizer = text("flavor/inc/class-customizer.php")
    design = text("flavor/inc/class-design.php")
    preview = text("flavor/assets/js/customizer-preview.js")
    scope = text("docs/HEADER-FOOTER-BUILDER-SCOPE.md")
    require(
        customizer,
        "flavor_gutter_mobile",
        "flavor_gutter_desktop",
        "flavor_heading_size_mobile",
        "flavor_heading_size_desktop",
        "sanitize_responsive",
    )
    require(
        design,
        "flavor-container-gutter-desktop",
        "flavor-heading-size-desktop",
        "flavor-section-space-desktop",
        "responsive_mod",
    )
    require(preview, "flavor_heading_size_mobile", "flavor_section_space_desktop")
    require(scope, "Header/Footer Builder", "پیاده‌سازی نشده است")


if __name__ == "__main__":
    for check in (test_onboarding_contract, test_import_transfer_contract, test_customizer_and_scope_contract):
        check()
    print("PASS implementation contract checks")
