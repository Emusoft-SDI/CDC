<?php
declare(strict_types=1);
?>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_profile">
      <section class="profile-section profile-tab-panel" data-profile-panel="personal">
        <h2>Account Settings</h2>
        <p class="hint">Update the profile details used for verification, support, and field engagement.</p>
        <div class="grid">
        <div><label>Profile Picture</label><input type="file" name="profile_picture" accept=".jpg,.jpeg,.png,.webp"></div>
        <div><label>Phone</label><input type="tel" name="phone" value="<?= e($profileForm['phone'] ?? '') ?>"></div>
        <div><label>Location</label><input type="text" name="location" value="<?= e($profileForm['location'] ?? '') ?>"></div>
        <div><label>Date of Birth</label><input type="date" name="dob" value="<?= e($profileForm['dob'] ?? '') ?>"></div>
        <div>
          <label>Marital Status</label>
          <select name="marital_status">
            <option value="">Select</option>
            <?php foreach (['single','married','divorced','widowed'] as $status): ?>
              <option value="<?= e($status) ?>" <?= ($profileForm['marital_status'] ?? '') === $status ? 'selected' : '' ?>><?= e(ucwords($status)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label>Family Size</label><input type="number" min="0" name="family_size" value="<?= e((string) ($profileForm['family_size'] ?? '')) ?>"></div>
        <div>
          <label>Education Level</label>
          <select name="education_level">
            <option value="">Select</option>
            <?php foreach (['none','primary','secondary','tertiary','post_graduate'] as $level): ?>
              <option value="<?= e($level) ?>" <?= ($profileForm['education_level'] ?? '') === $level ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $level))) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label>Farming Experience (Years)</label><input type="number" min="0" name="farming_experience_years" value="<?= e((string) ($profileForm['farming_experience_years'] ?? '')) ?>"></div>
        <div>
          <label>Experience Level</label>
          <select name="farming_experience_rating">
            <option value="">Select</option>
            <?php foreach (['beginner','intermediate','advanced','expert'] as $level): ?>
              <option value="<?= e($level) ?>" <?= ($profileForm['farming_experience_rating'] ?? '') === $level ? 'selected' : '' ?>><?= e(ucwords($level)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label>Next of Kin Name</label><input type="text" name="next_of_kin_name" value="<?= e($profileForm['next_of_kin_name'] ?? '') ?>"></div>
        <div><label>Next of Kin Phone</label><input type="tel" name="next_of_kin_phone" value="<?= e($profileForm['next_of_kin_phone'] ?? '') ?>"></div>
        <div><label>Relationship</label><input type="text" name="next_of_kin_relationship" value="<?= e($profileForm['next_of_kin_relationship'] ?? '') ?>"></div>
        </div>
      </section>

      <section class="profile-section profile-tab-panel" data-profile-panel="security" hidden>
        <h2>Security</h2>
        <p class="hint">Review the protections on your account and complete verification steps that improve trust and recovery.</p>
        <div class="grid">
          <div class="panel">
            <h3>Profile Verification</h3>
            <p><span class="badge <?= (int) ($profileForm['profile_verified'] ?? 0) === 1 ? 'verified' : 'pending' ?>"><?= (int) ($profileForm['profile_verified'] ?? 0) === 1 ? 'Verified' : 'Pending review' ?></span></p>
            <p class="muted">Verified profiles are easier for NATCODEV teams to support and validate.</p>
            <a class="button secondary" href="documents.php">Open Verification Documents</a>
          </div>
          <div class="panel">
            <h3>Phone Verification</h3>
            <p><strong><?= e($profileForm['phone'] ?? 'No phone number saved') ?></strong></p>
            <p class="muted">Use phone verification for recovery, alerts, and support workflows.</p>
            <a class="button secondary" href="verify-phone.php">Verify Phone</a>
          </div>
          <div class="panel">
            <h3>Critical Change OTP</h3>
            <p class="muted">Changing phone, location, next of kin, or notification channels may require a one-time security code.</p>
            <label>OTP for Critical Changes</label>
            <input type="text" name="otp_code" inputmode="numeric" maxlength="6" placeholder="<?= $otpRequired ? 'Enter 6-digit OTP' : 'Required only after OTP is requested' ?>">
            <button type="submit" form="send-profile-otp-form" class="secondary">Send OTP</button>
          </div>
        </div>
      </section>

      <?php if (($user['role'] ?? 'grower') === 'grower'): ?>
      <section class="profile-section profile-tab-panel" data-profile-panel="farm" hidden>
        <h2>Farm Information</h2>
        <p class="hint">Capture where the farm is, how large it is, and the main production conditions field teams should understand.</p>
        <?php if (empty($user['application_id'])): ?><p class="notice pending">This account is not linked to a registration application yet, so farm information cannot be saved.</p><?php endif; ?>
        <div class="grid">
          <div><label>Application Reference</label><input value="<?= e($profileForm['app_ref'] ?? 'Not linked') ?>" disabled></div>
          <div><label>Farm Size (Hectares)</label><input type="number" min="0" step="0.01" name="farm_size" value="<?= e((string) ($profileForm['farm_size'] ?? '')) ?>" <?= empty($user['application_id']) ? 'disabled' : '' ?>></div>
          <div>
            <label>Farm State</label>
            <select name="state_id" data-lga-target="primary_lga_id" <?= empty($user['application_id']) ? 'disabled' : '' ?>>
              <option value="">Select state</option>
              <?php foreach ($states as $state): ?>
                <option value="<?= (int) $state['id'] ?>" <?= (int) ($profileForm['state_id'] ?? 0) === (int) $state['id'] ? 'selected' : '' ?>><?= e($state['state_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Farm Local Government</label>
            <select id="primary_lga_id" name="lga_id" data-selected="<?= (int) ($profileForm['lga_id'] ?? 0) ?>" <?= empty($user['application_id']) ? 'disabled' : '' ?>>
              <option value="">Select LGA</option>
            </select>
          </div>
          <div><label>Farm Address / Community</label><input name="street_address" value="<?= e($profileForm['street_address'] ?? '') ?>" <?= empty($user['application_id']) ? 'disabled' : '' ?>></div>
          <div><label>Latitude</label><input id="primary_latitude" name="latitude" inputmode="decimal" value="<?= e((string) ($profileForm['latitude'] ?? '')) ?>" placeholder="e.g. 6.5244" <?= empty($user['application_id']) ? 'disabled' : '' ?>></div>
          <div><label>Longitude</label><input id="primary_longitude" name="longitude" inputmode="decimal" value="<?= e((string) ($profileForm['longitude'] ?? '')) ?>" placeholder="e.g. 3.3792" <?= empty($user['application_id']) ? 'disabled' : '' ?>></div>
          <div><label>Google Maps Link</label><input id="primary_maps_url" placeholder="Paste Google Maps link" <?= empty($user['application_id']) ? 'disabled' : '' ?>></div>
          <div><label>Extract From Link</label><button type="button" class="button secondary" data-map-extract="primary" <?= empty($user['application_id']) ? 'disabled' : '' ?>>Extract Latitude / Longitude</button></div>
          <div><label>Pick Farm Coordinates</label><button type="button" class="button secondary" data-location-fill="primary" <?= empty($user['application_id']) ? 'disabled' : '' ?>>Use My Current Location</button> <button type="button" class="button secondary" data-map-search="primary" <?= empty($user['application_id']) ? 'disabled' : '' ?>>Search Address On Map</button></div>
          <div><label>WhatsApp</label><input type="tel" name="whatsapp" value="<?= e($profileForm['whatsapp'] ?? '') ?>" <?= empty($user['application_id']) ? 'disabled' : '' ?>></div>
          <div>
            <label>Climate Zone</label>
            <select name="climate_zone" <?= empty($user['application_id']) ? 'disabled' : '' ?>>
              <option value="">Select climate zone</option>
              <?php foreach (profile_recommended_inputs('climate_zone') as $option): ?>
                <option value="<?= e($option) ?>" <?= profile_value_is_selected($profileForm['climate_zone'] ?? '', $option) ? 'selected' : '' ?>><?= e($option) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Topography</label>
            <select name="topography" <?= empty($user['application_id']) ? 'disabled' : '' ?>>
              <option value="">Select topography</option>
              <?php foreach (profile_recommended_inputs('topography') as $option): ?>
                <option value="<?= e($option) ?>" <?= profile_value_is_selected($profileForm['topography'] ?? '', $option) ? 'selected' : '' ?>><?= e($option) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Soil Type</label>
            <select name="soil_type" <?= empty($user['application_id']) ? 'disabled' : '' ?>>
              <option value="">Select soil type</option>
              <?php foreach (profile_recommended_inputs('soil_type') as $option): ?>
                <option value="<?= e($option) ?>" <?= profile_value_is_selected($profileForm['soil_type'] ?? '', $option) ? 'selected' : '' ?>><?= e($option) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Water Source</label>
            <select name="water_source" <?= empty($user['application_id']) ? 'disabled' : '' ?>>
              <option value="">Select water source</option>
              <?php foreach (profile_recommended_inputs('water_source') as $option): ?>
                <option value="<?= e($option) ?>" <?= profile_value_is_selected($profileForm['water_source'] ?? '', $option) ? 'selected' : '' ?>><?= e($option) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Irrigation Method</label>
            <select name="irrigation_method" <?= empty($user['application_id']) ? 'disabled' : '' ?>>
              <option value="">Select irrigation method</option>
              <?php foreach (profile_recommended_inputs('irrigation_method') as $option): ?>
                <option value="<?= e($option) ?>" <?= profile_value_is_selected($profileForm['irrigation_method'] ?? '', $option) ? 'selected' : '' ?>><?= e($option) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Land Ownership Status</label>
            <select name="land_ownership_status" <?= empty($user['application_id']) ? 'disabled' : '' ?>>
              <option value="">Select land ownership</option>
              <?php foreach (profile_recommended_inputs('land_ownership_status') as $status): ?>
                <option value="<?= e($status) ?>" <?= profile_value_is_selected($profileForm['land_ownership_status'] ?? '', $status) ? 'selected' : '' ?>><?= e($status) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><label>Land Title Details</label><input list="land_title_options" name="land_title_details" value="<?= e($profileForm['land_title_details'] ?? '') ?>" placeholder="Select or type title details" <?= empty($user['application_id']) ? 'disabled' : '' ?>></div>
        </div>
      </section>

      <section class="profile-section profile-tab-panel" data-profile-panel="activity" hidden>
        <h2>What You Are Doing On The Farm</h2>
        <p class="hint">This helps NATCODEV understand the grower’s coconut varieties, intercrops, livestock integration, stage of production, and support needs.</p>
        <div class="grid">
          <div>
            <label>Coconut Variety Cultivated</label>
            <select name="coconut_variety" <?= empty($user['application_id']) ? 'disabled' : '' ?>>
              <option value="">Select coconut variety</option>
              <?php foreach (profile_coconut_varieties() as $variety): ?>
                <option value="<?= e($variety) ?>" <?= ($profileForm['coconut_variety'] ?? '') === $variety ? 'selected' : '' ?>><?= e($variety) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><label>Intercrops (if any)</label><input list="intercrop_options" name="intercrops" value="<?= e($profileForm['intercrops'] ?? '') ?>" placeholder="Select or type crops, comma separated" <?= empty($user['application_id']) ? 'disabled' : '' ?>></div>
          <div><label>Poultry / Livestock Integration</label><input list="livestock_options" name="livestock_integration" value="<?= e($profileForm['livestock_integration'] ?? '') ?>" placeholder="Select or type livestock integration" <?= empty($user['application_id']) ? 'disabled' : '' ?>></div>
          <div>
            <label>Production Stage</label>
            <select name="production_stage" <?= empty($user['application_id']) ? 'disabled' : '' ?>>
              <option value="">Select production stage</option>
              <?php foreach (profile_recommended_inputs('production_stage') as $stage): ?>
                <option value="<?= e($stage) ?>" <?= profile_value_is_selected($profileForm['production_stage'] ?? '', $stage) ? 'selected' : '' ?>><?= e($stage) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><label>Estimated Coconut Trees</label><input type="number" min="0" name="estimated_tree_count" value="<?= e((string) ($profileForm['estimated_tree_count'] ?? '')) ?>" <?= empty($user['application_id']) ? 'disabled' : '' ?>></div>
          <div><label>Annual Yield Estimate</label><input list="yield_options" name="annual_yield_estimate" value="<?= e($profileForm['annual_yield_estimate'] ?? '') ?>" placeholder="Select or type yield estimate" <?= empty($user['application_id']) ? 'disabled' : '' ?>></div>
          <div><label>Market Channels</label><input list="market_options" name="market_channels" value="<?= e($profileForm['market_channels'] ?? '') ?>" placeholder="Select or type market channels" <?= empty($user['application_id']) ? 'disabled' : '' ?>></div>
          <div><label>Current Farm Activities</label><textarea name="current_farm_activities" placeholder="Examples: nursery raising, land clearing, transplanting, weeding, mulching, harvesting, processing, farm expansion" <?= empty($user['application_id']) ? 'disabled' : '' ?>><?= e($profileForm['current_farm_activities'] ?? '') ?></textarea></div>
          <div><label>Farm Practices / Certification</label><textarea name="farming_practices" placeholder="Organic practices, fertilizer use, pest control, GAP, certificates..." <?= empty($user['application_id']) ? 'disabled' : '' ?>><?= e($profileForm['farming_practices'] ?? '') ?></textarea></div>
          <div><label>Major Challenges</label><textarea name="major_challenges" placeholder="Inputs, finance, pests, labour, seedlings, market access..." <?= empty($user['application_id']) ? 'disabled' : '' ?>><?= e($profileForm['major_challenges'] ?? '') ?></textarea></div>
          <div><label>Support Needed</label><textarea name="support_needs" placeholder="Training, seedlings, finance, extension visits, certification..." <?= empty($user['application_id']) ? 'disabled' : '' ?>><?= e($profileForm['support_needs'] ?? '') ?></textarea></div>
        </div>
        <?php foreach ([
            'land_title_options' => 'land_title_details',
            'intercrop_options' => 'intercrops',
            'livestock_options' => 'livestock_integration',
            'yield_options' => 'annual_yield_estimate',
            'market_options' => 'market_channels',
        ] as $listId => $source): ?>
          <datalist id="<?= e($listId) ?>">
            <?php foreach (profile_recommended_inputs($source) as $option): ?>
              <option value="<?= e($option) ?>"></option>
            <?php endforeach; ?>
          </datalist>
        <?php endforeach; ?>
      </section>
      <?php endif; ?>

      <section class="profile-section profile-tab-panel" data-profile-panel="notifications" hidden>
        <h2>Notification Preferences</h2>
      <label class="check"><input type="checkbox" name="notify_email" <?= (int) ($profileForm['notify_email'] ?? 1) === 1 ? 'checked' : '' ?>> Email notifications</label><br>
      <label class="check"><input type="checkbox" name="notify_whatsapp" <?= (int) ($profileForm['notify_whatsapp'] ?? 0) === 1 ? 'checked' : '' ?>> WhatsApp notifications</label><br>
      <label class="check"><input type="checkbox" name="notify_sms" <?= (int) ($profileForm['notify_sms'] ?? 0) === 1 ? 'checked' : '' ?>> SMS notifications</label><br><br>
      </section>
      <div class="profile-actions" data-profile-save-actions><button type="submit">Save Profile</button></div>
    </form>
    <form id="send-profile-otp-form" method="post" hidden>
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="send_profile_otp">
    </form>
    <section class="profile-section profile-tab-panel" data-profile-panel="password" hidden>
      <h2>Password</h2>
      <p class="hint">Change your password regularly and use at least 8 characters with a mix of letters, numbers, and symbols.</p>
      <form method="post" class="panel">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="update_password">
        <div class="grid">
          <div><label>Current Password</label><input type="password" name="current_password" required autocomplete="current-password"></div>
          <div><label>New Password</label><input type="password" name="new_password" minlength="8" required autocomplete="new-password"></div>
          <div><label>Confirm New Password</label><input type="password" name="confirm_password" minlength="8" required autocomplete="new-password"></div>
        </div>
        <button type="submit">Update Password</button>
      </form>
    </section>
    <?php if (($user['role'] ?? 'grower') === 'grower'): ?>
      <section class="profile-section profile-tab-panel" data-profile-panel="locations" hidden>
        <h2>Farm Locations</h2>
        <p class="hint">A grower can have more than one farm. Add each farm separately with State, LGA, address, and GPS coordinates.</p>
        <div class="grid">
          <?php foreach ($growerFarms as $farm): ?>
            <form method="post" class="panel">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="update_farm">
              <input type="hidden" name="farm_id" value="<?= (int) $farm['id'] ?>">
              <h3><?= e($farm['farm_name']) ?><?= (int) $farm['is_primary'] === 1 ? ' (Primary)' : '' ?></h3>
              <p><span class="badge <?= e((string) ($farm['verification_status'] ?? 'pending')) ?>"><?= e(ucwords(str_replace('_', ' ', (string) ($farm['verification_status'] ?? 'pending')))) ?></span>
              <?php if ($farm['system_confidence_score'] !== null): ?><span class="muted">System confidence: <?= e(number_format((float) $farm['system_confidence_score'], 1)) ?>%</span><?php endif; ?></p>
              <?php if (!empty($farm['system_notes'])): ?><p class="muted"><?= e((string) $farm['system_notes']) ?></p><?php endif; ?>
              <div class="grid">
                <div><label>Farm Name</label><input name="farm_name" value="<?= e($farm['farm_name']) ?>"></div>
                <div><label>Farm Size (Hectares)</label><input type="number" min="0" step="0.01" name="farm_record_size" value="<?= e((string) ($farm['farm_size'] ?? '')) ?>"></div>
                <div>
                  <label>State</label>
                  <select name="farm_state_id" data-lga-target="farm_lga_<?= (int) $farm['id'] ?>">
                    <option value="">Select state</option>
                    <?php foreach ($states as $state): ?>
                      <option value="<?= (int) $state['id'] ?>" <?= (int) ($farm['state_id'] ?? 0) === (int) $state['id'] ? 'selected' : '' ?>><?= e($state['state_name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label>Local Government</label>
                  <select id="farm_lga_<?= (int) $farm['id'] ?>" name="farm_lga_id" data-selected="<?= (int) ($farm['lga_id'] ?? 0) ?>">
                    <option value="">Select LGA</option>
                  </select>
                </div>
                <div><label>Farm Address / Community</label><input name="farm_street_address" value="<?= e($farm['street_address'] ?? '') ?>"></div>
                <div><label>Latitude</label><input id="farm_latitude_<?= (int) $farm['id'] ?>" name="farm_latitude" inputmode="decimal" value="<?= e((string) ($farm['latitude'] ?? '')) ?>"></div>
                <div><label>Longitude</label><input id="farm_longitude_<?= (int) $farm['id'] ?>" name="farm_longitude" inputmode="decimal" value="<?= e((string) ($farm['longitude'] ?? '')) ?>"></div>
                <div><label>Google Maps Link</label><input id="farm_<?= (int) $farm['id'] ?>_maps_url" placeholder="Paste Google Maps link"></div>
                <div><label>Extract From Link</label><button type="button" class="button secondary" data-map-extract="farm_<?= (int) $farm['id'] ?>">Extract Latitude / Longitude</button></div>
                <div><label>Pick Coordinates</label><button type="button" class="button secondary" data-location-fill="farm_<?= (int) $farm['id'] ?>">Use My Current Location</button> <button type="button" class="button secondary" data-map-search="farm_<?= (int) $farm['id'] ?>">Search Address On Map</button></div>
              </div>
              <button type="submit">Save Farm</button>
            </form>
          <?php endforeach; ?>

          <form method="post" class="panel">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_farm">
            <h3>Add Another Farm</h3>
            <div class="grid">
              <div><label>Farm Name</label><input name="farm_name" placeholder="e.g. Riverbank Farm"></div>
              <div><label>Farm Size (Hectares)</label><input type="number" min="0" step="0.01" name="farm_record_size"></div>
              <div>
                <label>State</label>
                <select name="farm_state_id" data-lga-target="new_farm_lga">
                  <option value="">Select state</option>
                  <?php foreach ($states as $state): ?>
                    <option value="<?= (int) $state['id'] ?>"><?= e($state['state_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div><label>Local Government</label><select id="new_farm_lga" name="farm_lga_id"><option value="">Select LGA</option></select></div>
              <div><label>Farm Address / Community</label><input name="farm_street_address"></div>
              <div><label>Latitude</label><input id="new_farm_latitude" name="farm_latitude" inputmode="decimal"></div>
              <div><label>Longitude</label><input id="new_farm_longitude" name="farm_longitude" inputmode="decimal"></div>
              <div><label>Google Maps Link</label><input id="new_farm_maps_url" placeholder="Paste Google Maps link"></div>
              <div><label>Extract From Link</label><button type="button" class="button secondary" data-map-extract="new_farm">Extract Latitude / Longitude</button></div>
              <div><label>Pick Coordinates</label><button type="button" class="button secondary" data-location-fill="new_farm">Use My Current Location</button> <button type="button" class="button secondary" data-map-search="new_farm">Search Address On Map</button></div>
            </div>
            <button type="submit">Add Farm</button>
          </form>
        </div>
      </section>
    <?php endif; ?>
    <script src="../assets/js/farm-profile.js"></script>
    <?php if (!empty($user['staff_type'])): ?>
      <section class="panel" style="margin-top:18px;">
        <h2>Staff Profile</h2>
        <div class="grid">
          <p><strong>Staff Type</strong><br><?= e(ucwords(str_replace('_', ' ', (string) $user['staff_type']))) ?></p>
          <p><strong>Qualification</strong><br><?= e($user['qualification'] ?? 'Not set') ?></p>
          <p><strong>License / Certification</strong><br><?= e($user['license_number'] ?? 'Not set') ?></p>
          <p><strong>Experience</strong><br><?= e((string) ($user['experience_years'] ?? '0')) ?> years</p>
          <p><strong>Training Program</strong><br><?= e($user['training_program'] ?? 'Not set') ?></p>
          <p><strong>Certification Status</strong><br><?= e(ucwords(str_replace('_', ' ', (string) ($user['certification_status'] ?? 'not_started')))) ?></p>
        </div>
      </section>
    <?php endif; ?>
