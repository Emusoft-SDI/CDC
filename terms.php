<?php
declare(strict_types=1);

/**
 * Terms of Service.
 *
 * Previously this page did not exist, yet the footer linked to it (along with four
 * other legal pages) from about-partials/footer.php.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/public-page.php';

$lastUpdated = '21 September 2026';

public_page_head(
    'Terms of Service',
    'The terms that govern your use of the NATCODEV platform, registry, marketplace and academy.'
);
?>
<article class="pc-doc">
  <h1>Terms of Service</h1>
  <p class="pc-meta">Last updated: <?= e($lastUpdated) ?></p>

  <div class="pc-note">
    <p><strong>In short:</strong> use the platform honestly, keep your registration and farm
    details accurate, pay for what you buy, and deliver what you sell. We may suspend accounts
    that break these rules or that put other users at risk.</p>
  </div>

  <nav class="pc-toc" aria-label="Contents">
    <ol>
      <li><a href="#who-we-are">Who we are</a></li>
      <li><a href="#acceptance">Accepting these terms</a></li>
      <li><a href="#eligibility">Eligibility and accounts</a></li>
      <li><a href="#registry">Registry and application accuracy</a></li>
      <li><a href="#marketplace">Marketplace rules</a></li>
      <li><a href="#payments">Payments, fees and commission</a></li>
      <li><a href="#academy">Academy and certificates</a></li>
      <li><a href="#conduct">Acceptable use</a></li>
      <li><a href="#ip">Intellectual property</a></li>
      <li><a href="#suspension">Suspension and termination</a></li>
      <li><a href="#liability">Disclaimers and liability</a></li>
      <li><a href="#law">Governing law</a></li>
      <li><a href="#changes">Changes to these terms</a></li>
      <li><a href="#contact">Contact</a></li>
    </ol>
  </nav>

  <h2 id="who-we-are">1. Who we are</h2>
  <p>
    NATCODEV is the National Coconut Development &amp; Propagation Initiative. We operate this
    platform to register growers, map farms, connect producers with buyers and service
    providers, deliver training through the Academy, and verify certificates and credentials
    across the Nigerian coconut value chain.
  </p>
  <p>
    In these terms, "the platform" means this website and every service reachable through it,
    including the registry, marketplace, academy, wallet and support desk. "We" and "us" mean
    NATCODEV. "You" means the person or organisation using the platform.
  </p>

  <h2 id="acceptance">2. Accepting these terms</h2>
  <p>
    By creating an account, submitting a registration, listing an item, placing an order or
    otherwise using the platform, you agree to these terms and to our
    <a href="privacy.php">Privacy Policy</a>. If you do not agree, please do not use the platform.
  </p>

  <h2 id="eligibility">3. Eligibility and accounts</h2>
  <ul>
    <li>You must be at least 18 years old to hold an account.</li>
    <li>You must give accurate, current information, and keep it up to date.</li>
    <li>You are responsible for keeping your password and any one-time codes confidential.</li>
    <li>You are responsible for everything done through your account. Tell us immediately if you suspect unauthorised access.</li>
    <li>One person or organisation should hold one account. Accounts are not transferable without our agreement.</li>
  </ul>

  <h2 id="registry">4. Registry and application accuracy</h2>
  <p>
    Registration data underpins verification, eligibility for programmes and grant allocation,
    so accuracy matters:
  </p>
  <ul>
    <li>Provide truthful identity, contact, cooperative and farm information.</li>
    <li>Farm boundaries and geospatial data you submit must relate to land you farm or lawfully represent.</li>
    <li>Identity and document checks may be carried out, and you agree to provide documents we reasonably request.</li>
    <li>Submitting false information, or registering on behalf of others without authority, may result in rejection or removal.</li>
  </ul>

  <h2 id="marketplace">5. Marketplace rules</h2>
  <h3>For sellers</h3>
  <ul>
    <li>List only goods you are lawfully entitled to sell, with accurate descriptions, quantities, units and prices.</li>
    <li>Honour confirmed orders, and dispatch within the timeframe you state.</li>
    <li>Keep stock information current. Orders may be cancelled if stock cannot be honoured.</li>
    <li>Agricultural inputs and biological materials must comply with applicable Nigerian regulation.</li>
  </ul>
  <h3>For buyers</h3>
  <ul>
    <li>Pay the amounts you commit to when you place an order.</li>
    <li>Provide accurate delivery details and be reachable at the contact you give.</li>
    <li>Raise disputes promptly and in good faith, using the order and dispute tools provided.</li>
  </ul>
  <p>
    We provide the platform that connects buyers and sellers. Unless we expressly state otherwise,
    the contract of sale is between the buyer and the seller, and the seller is responsible for the
    goods, their quality and their delivery.
  </p>

  <h2 id="payments">6. Payments, fees and commission</h2>
  <ul>
    <li>Payments are processed through licensed third-party payment providers. We do not store full card details.</li>
    <li>A commission or service fee may apply to marketplace sales, and will be shown before you confirm a sale.</li>
    <li>Wallet balances, where offered, are a record of funds held for you in connection with platform activity. They are not a bank deposit.</li>
    <li>Withdrawals are subject to verification and may be delayed where we are required to check the source of funds or comply with law.</li>
    <li>You are responsible for any tax arising from your activity on the platform.</li>
  </ul>

  <h2 id="academy">7. Academy and certificates</h2>
  <ul>
    <li>Course materials are for your own learning and are licensed, not sold, to you.</li>
    <li>Certificates are issued on completion of the stated requirements. They may be verified publicly using their reference.</li>
    <li>We may revoke a certificate obtained through cheating, impersonation or fraud, and will mark it as revoked in the public verification service.</li>
    <li>A certificate records training completion. It is not a professional licence and does not replace any statutory certification.</li>
  </ul>

  <h2 id="conduct">8. Acceptable use</h2>
  <p>You must not:</p>
  <ul>
    <li>break the law, or help anyone else to;</li>
    <li>impersonate another person, or misrepresent your affiliation;</li>
    <li>attempt to gain access to accounts, systems or data that are not yours;</li>
    <li>probe, scan or overload the platform, or circumvent rate limits, authentication or payment verification;</li>
    <li>upload malware, or content that is unlawful, hateful, defamatory, or infringes another person's rights;</li>
    <li>scrape or bulk-copy the platform or its data without written permission;</li>
    <li>use the platform to send unsolicited commercial messages.</li>
  </ul>

  <h2 id="ip">9. Intellectual property</h2>
  <p>
    The platform, its design, software, training content and the NATCODEV marks belong to us or our
    licensors. You may use the platform as intended, but you may not copy, resell or create
    derivative works from it without our written consent. Content you upload remains yours; you
    grant us the licence we need to host, display and process it in order to provide the service.
  </p>

  <h2 id="suspension">10. Suspension and termination</h2>
  <p>
    We may suspend or close an account, remove a listing, or withhold a withdrawal where we
    reasonably believe these terms have been broken, where we are required to by law, or where
    doing so is necessary to protect other users or the platform. Where it is appropriate and
    lawful to do so, we will tell you why. You may close your account at any time.
  </p>

  <h2 id="liability">11. Disclaimers and liability</h2>
  <p>
    The platform is provided on an "as available" basis. We work to keep it accurate and running,
    but we do not promise uninterrupted or error-free service, and some information (for example
    advisory content, or a seller's listing) is provided by others.
  </p>
  <p>
    To the extent permitted by Nigerian law, we are not liable for indirect or consequential loss,
    loss of profit, or loss of anticipated harvest, and our total liability in connection with the
    platform is limited to the amount of platform fees you paid to us in the twelve months before
    the event giving rise to the claim. Nothing in these terms limits liability that cannot
    lawfully be limited.
  </p>

  <h2 id="law">12. Governing law</h2>
  <p>
    These terms are governed by the laws of the Federal Republic of Nigeria, and the courts of
    Nigeria have jurisdiction over any dispute arising from them. We encourage you to contact us
    first so that we can try to resolve the matter directly.
  </p>

  <h2 id="changes">13. Changes to these terms</h2>
  <p>
    We may update these terms as the platform develops. When we do, we will change the "last
    updated" date above, and where the change is significant we will give notice on the platform.
    Continuing to use the platform after a change means you accept the updated terms.
  </p>

  <h2 id="contact">14. Contact</h2>
  <p>
    Questions about these terms:
    <?php $office = app_contact_office(); ?>
    <a href="mailto:<?= e((string) $office['email']) ?>"><?= e((string) $office['email']) ?></a>
    or <a href="tel:<?= e((string) $office['phone_tel']) ?>"><?= e((string) $office['phone_display']) ?></a>.
    You can also <a href="contact.php">send us a message</a> or write to us at
    <?= e(implode(', ', $office['address_lines'])) ?>.
  </p>

  <a class="pc-back" href="index.php">&larr; Back to home</a>
</article>
<?php
public_page_foot();
