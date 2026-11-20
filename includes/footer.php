</div><!-- /page -->

<div class="wrap footer">
  <div><?= e($hospital_name ?? 'General Hospital') ?> &middot; Injury Surveillance</div>
  <?php
  $hospital_settings = $hospital_settings ?? hospital_defaults();
  $contact_parts = [];
  foreach (['hospital_phone', 'hospital_email', 'hospital_address'] as $contact_key) {
      if (trim((string) ($hospital_settings[$contact_key] ?? '')) !== '') {
          $contact_parts[] = e($hospital_settings[$contact_key]);
      }
  }
  ?>
  <?php if ($contact_parts): ?>
    <div class="footer-contact"><?= implode(' &middot; ', $contact_parts) ?></div>
  <?php endif; ?>
</div>

<script src="assets/app.js"></script>
</body>
</html>
