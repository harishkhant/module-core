# Changelog

## 1.0.1
- New RevaMagento branding: updated logo in the admin sidebar menu, the configuration tab and the information panel.
- Redesigned the Information & Marketplace panel: company introduction, Reva extensions with installed status,
  Magento services, and working support links (email, phone, website, privacy policy) instead of placeholders.
- The MageReva admin menu is now highlighted when a Reva configuration page opened from it is shown
  (Magento highlighted Stores instead).
- Licensed under OSL 3.0 / AFL 3.0; LICENSE.txt and LICENSE_AFL.txt are now included in the package.
- Compatible with Magento Open Source / Adobe Commerce 2.4.4 - 2.4.9 and PHP 8.1 - 8.5.
- Added the ACL resources `Reva_Core::reva_menu` (MageReva admin menu) and `Reva_Core::information`
  (Information & Marketplace configuration section), which were referenced but not defined.
- Color picker: the stored value is limited to hex digits and the element id is escaped before being written
  into the admin script.
- Declared Magento module dependencies and module load order; removed the bundled composer.lock.
- Added unit tests.
