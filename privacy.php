<?php
declare(strict_types=1);

/**
 * Privacy Policy.
 *
 * Previously this page did not exist, yet the footer linked to it (along with four
 * other legal pages) from about-partials/footer.php.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/public-page.php';

$lastUpdated = '21 September 2026';
$office = app_contact_office();

public_page_head(
    'Privacy Policy',
    'What personal data NATCODEV collects, why we collect it, who we share it with, and the rights you have under the Nigeria Data Protection Act.'
);
?>
<article class="pc-doc">
  <h1>Privacy Policy</h1>
  <p class="pc-meta">Last updated: <?= e($lastUpdated) ?></p>

  <div class="pc-note">
    <p><strong>In short:</strong> we collect what we need to register and verify growers, map
    farms, run the marketplace and academy, and pay people correctly. We do not sell your
    personal data. You can ask to see, correct or delete what we hold.</p>
  </div>

  <nav class="pc-toc" aria-label="Contents">
    <ol>
      <li><a href="#controller">Who controls your data</a></li>
      <li><a href="#collect">What we collect</a></li>
      <li><a href="#why">Why we use it</a></li>
      <li><a href="#cookies">Cookies and similar technologies</a></li>
      <li><a href="#share">Who we share it with</a></li>
      <li><a href="#retention">How long we keep it</a></li>
      <li><a href="#security">How we protect it</a></li>
      <li><a href="#rights">Your rights</a></li>
      <li><a href="#transfers">Transfers outside Nigeria</a></li>
      <li><a href="#children">Children</a></li>
      <li><a href="#changes">Changes to this policy</a></li>
      <li><a href="#contact">Contacting us</a></li>
    </ol>
  </nav>

  <h2 id="controller">1. Who controls your data</h2>
  <p>
    NATCODEV, the National Coconut Development &amp; Propagation Initiative, is the data controller
    for personal data processed through this platform. Our contact details are in
    <a href="#contact">section 12</a>.
  </p>
  <p>
    We handle personal data in line with the Nigeria Data Protection Act 2023 (NDPA) and other
    applicable Nigerian law.
  </p>

  <h2 id="collect">2. What we collect</h2>
  <h3>Information you give us</h3>
  <ul>
    <li><strong>Identity and contact details</strong> — name, phone number, email address, and where you provide them, date of birth, gender and address.</li>
    <li><strong>Registration and eligibility data</strong> — cooperative or association membership, role applied for, and the answers you give in an application.</li>
    <li><strong>Verification documents</strong> — identity documents, and supporting documents such as proof of land or membership, submitted for verification.</li>
    <li><strong>Farm data</strong> — farm location, boundaries and geospatial coordinates, size, crop and tree counts, and farming practices.</li>
    <li><strong>Marketplace and payment data</strong> — listings, orders, delivery details, bank account or settlement details, and transaction references.</li>
    <li><strong>Academy data</strong> — courses enrolled in, progress, assessment results and certificates issued.</li>
    <li><strong>Support data</strong> — messages, attachments and call notes when you contact the support desk.</li>
  </ul>
  <h3>Information we collect automatically</h3>
  <ul>
    <li><strong>Technical data</strong> — IP address, browser and device type, and the pages you visit, used for security and to keep the service working.</li>
    <li><strong>Cookie data</strong> — as described in <a href="#cookies">section 4</a>.</li>
  </ul>

  <h2 id="why">3. Why we use it</h2>
  <p>
    We use personal data to run the platform and to meet our obligations. The table below sets out
    what we do and the lawful basis we rely on under the NDPA.
  </p>
  <ul>
    <li><strong>To create and secure your account</strong> — contract, and our legitimate interest in preventing fraud.</li>
    <li><strong>To assess applications, verify identity and confirm eligibility</strong> — contract, your consent for documents you volunteer, and our public-interest role in maintaining an accurate grower registry.</li>
    <li><strong>To map farms and plan extension support</strong> — our public-interest role, and consent for precise geospatial data.</li>
    <li><strong>To operate the marketplace</strong> — contract, so buyers and sellers can transact, and to resolve disputes.</li>
    <li><strong>To process payments and settle funds</strong> — contract, and our legal obligations on financial records and anti-fraud checks.</li>
    <li><strong>To deliver training and issue certificates</strong> — contract, and our legitimate interest in a traceable certificate register.</li>
    <li><strong>To answer support requests</strong> — contract and legitimate interest.</li>
    <li><strong>To send service messages</strong> (verification codes, order updates) — contract. Marketing messages are sent only with your consent and you can opt out at any time.</li>
    <li><strong>To keep the platform secure and detect misuse</strong> — legitimate interest, and our legal obligations.</li>
  </ul>

  <h2 id="cookies">4. Cookies and similar technologies</h2>
  <p>We use three kinds of cookie and local storage:</p>
  <ul>
    <li><strong>Essential</strong> — needed to keep you signed in on a secure connection, to protect forms against cross-site request forgery, and to remember your cookie choice. The platform cannot work without these.</li>
    <li><strong>Analytics</strong> — if you consent, we measure which pages and features are used so we can improve them. These are optional.</li>
    <li><strong>Preference</strong> — remembering interface choices you make.</li>
  </ul>
  <p>
    You can change or withdraw your choice at any time:
    <button type="button" class="pc-cta" data-nc-cookie-settings style="margin-left:6px">Open cookie settings</button>
  </p>
  <p>
    You can also block or delete cookies in your browser settings. Blocking essential cookies will
    stop parts of the platform, including sign-in, from working.
  </p>

  <h2 id="share">5. Who we share it with</h2>
  <p>We share personal data only where we need to, and only as far as necessary:</p>
  <ul>
    <li><strong>Payment providers</strong> — licensed processors handle card, transfer and wallet payments. We do not store full card numbers.</li>
    <li><strong>Messaging providers</strong> — to deliver SMS and email, including verification codes and order notifications.</li>
    <li><strong>Hosting and infrastructure providers</strong> — who store our data and run the platform on our instructions.</li>
    <li><strong>Government and programme partners</strong> — where the registry supports a public agricultural programme, and where reporting is required by law.</li>
    <li><strong>Other platform users</strong> — a marketplace counterparty sees the details needed to fulfil an order, such as your name, contact and delivery information. Sellers see order details; buyers see the seller's trading name.</li>
    <li><strong>Professional advisers and authorities</strong> — where required by law, or to establish or defend legal claims.</li>
  </ul>
  <p>
    <strong>We do not sell your personal data.</strong> We do not share your identity documents or
    precise farm coordinates publicly.
  </p>

  <h2 id="retention">6. How long we keep it</h2>
  <ul>
    <li><strong>Account and registry records</strong> — for as long as your account is open, and afterwards for as long as needed to evidence entitlement, programme eligibility and certificate validity.</li>
    <li><strong>Verification documents</strong> — for the period required to evidence the verification, then deleted or archived.</li>
    <li><strong>Financial and transaction records</strong> — for the period required by Nigerian tax and financial record-keeping rules.</li>
    <li><strong>Support conversations</strong> — for a reasonable period so we can resolve follow-ups.</li>
    <li><strong>Verification codes</strong> — short-lived, and purged once used or expired.</li>
  </ul>
  <p>When a retention period ends, we delete or anonymise the data.</p>

  <h2 id="security">7. How we protect it</h2>
  <ul>
    <li>Passwords are stored only as salted one-way hashes, and legacy formats are upgraded on sign-in.</li>
    <li>Sign-in for privileged accounts requires a second factor delivered to the account holder.</li>
    <li>Connections to the platform are encrypted in transit, and access to administrative tools is restricted and logged.</li>
    <li>Payment and SMS provider callbacks are verified by signature before any balance is credited.</li>
  </ul>
  <p>
    No system is perfectly secure. If a breach affects your personal data, we will notify you and
    the Nigeria Data Protection Commission as required.
  </p>

  <h2 id="rights">8. Your rights</h2>
  <p>Under the NDPA you have the right to:</p>
  <ul>
    <li>be informed about how your data is used;</li>
    <li>access the personal data we hold about you;</li>
    <li>ask us to correct data that is inaccurate or incomplete;</li>
    <li>ask us to delete data we no longer need;</li>
    <li>restrict or object to certain processing;</li>
    <li>receive data you provided in a portable format;</li>
    <li>withdraw consent you previously gave (for example for marketing or analytics);</li>
    <li>complain to the Nigeria Data Protection Commission.</li>
  </ul>
  <p>
    To exercise any of these, contact us using the details in <a href="#contact">section 12</a>. We
    will respond within the period the NDPA allows. We may need to confirm your identity first.
  </p>

  <h2 id="transfers">9. Transfers outside Nigeria</h2>
  <p>
    Some of our service providers process data outside Nigeria. Where that happens, we take steps
    to ensure your data receives an adequate level of protection, using the safeguards the NDPA
    provides for international transfers.
  </p>

  <h2 id="children">10. Children</h2>
  <p>
    The platform is for adults aged 18 and over. We do not knowingly collect data from children. If
    you believe a child has provided us with personal data, contact us and we will remove it.
  </p>

  <h2 id="changes">11. Changes to this policy</h2>
  <p>
    We may update this policy as the platform develops. We will change the "last updated" date
    above, and where the change is significant we will give notice on the platform.
  </p>

  <h2 id="contact">12. Contacting us</h2>
  <p>For any privacy question, or to exercise your rights:</p>
  <ul>
    <li><strong>Email</strong> — <a href="mailto:<?= e((string) $office['email']) ?>"><?= e((string) $office['email']) ?></a></li>
    <li><strong>Phone</strong> — <a href="tel:<?= e((string) $office['phone_tel']) ?>"><?= e((string) $office['phone_display']) ?></a></li>
    <li><strong>Address</strong> — <?= e(implode(', ', $office['address_lines'])) ?></li>
    <li><strong>Message</strong> — <a href="contact.php">use the contact form</a></li>
  </ul>
  <p>
    If you are not satisfied with our response, you may complain to the Nigeria Data Protection
    Commission (NDPC).
  </p>

  <a class="pc-back" href="index.php">&larr; Back to home</a>
</article>
<?php
public_page_foot();
