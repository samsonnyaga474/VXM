# VXM — Business details still required

These items cannot be invented. They must be supplied by the platform owner and, where relevant, reviewed by qualified Kenyan counsel.

## Legal identity
- [ ] Verified legal / trading name
- [ ] Physical or registered business address
- [ ] Company / business registration number (if applicable)
- [ ] Privacy contact email
- [ ] Support email
- [ ] Support phone
- [ ] Data Protection Officer contact (if required)
- [ ] Governing law and dispute process text

## Financial rules
- [ ] Official task reward rate card (or confirmation that admin-configured values are the official source)
- [ ] Official daily earning targets, if any (the old “KES 20 / 60 / 140” text is descriptive only)
- [ ] Whether XP will ever affect money, levels, or eligibility (currently: **no conversion**)
- [ ] Refund eligibility, window, method, and exceptions
- [ ] Withdrawal processing times
- [ ] Whether Pro daily task limit should be 15 or 20 (seeds historically differed; current live schema used 15 after correction)

## Payments
- [ ] Production M-Pesa / Daraja credentials
- [ ] Production callback URL
- [ ] Confirmed paybill / till / send-money number
- [ ] Live callback testing sign-off

## Tracking
- [ ] Whether any analytics or advertising pixels will be added later
- [ ] If yes: purpose, lawful basis, and consent design

## Assets
- [ ] Confirmation that `images/logo.jpg` and favicons are owned or licensed by VXM

## LIVE SAFARICOM TESTING REQUIRED

Production M-Pesa cannot be marked ready until real Daraja credentials and a public callback URL are configured and a live STK + callback cycle is tested against Safaricom. Simulated deposits work only when VXM_ENV=development and ALLOW_SIMULATED_DEPOSITS=true.
