# TwoFactor::check( $user )

## Purpose
Verifies if a user's Two-Factor Authentication (2FA) session is still valid.
If not, prompts for OTP verification to enhance security.

## Process Flow
1. Define Session and OTP Lifetimes
    OTP Session Lifetime → 24 hours (86,400 seconds)
    OTP Token Lifetime → 5 minutes (300 seconds)

2. Check if 2FA is Enabled
    If the user has 2FA disabled, skip verification and allow login.

3. Validate Existing OTP Session
    If the user has a previously verified OTP session:
        Ensure the session is still within its validity period.
        Check that the IP address remains unchanged.
        Check that the browser (User-Agent) remains unchanged.
        If all checks pass, skip 2FA.

4. Handle Email-Based OTP
    Look for an unused OTP token in the database:
        The token must be new (not used before).
        The token must not be expired.
    If a valid OTP token exists:
        Check if the IP or User-Agent has changed.
        If changed, invalidate the token, log out the user, and redirect to login.
    If no valid OTP token exists:
        Generate a new OTP and send it via email.
    Redirect to the OTP entry page for verification.

5. Handle App-Based (Time-Based) OTP
    Also known as Time-Based OTP (TOTP)
    Requires an authentication app (Google Authenticator, Authy, etc.).
    Redirect user to the OTP verification page for app-based authentication.

## Security Considerations
    If the user’s IP or User-Agent changes, the system will invalidate OTP tokens to prevent unauthorized access.
    App-Based OTP is generated dynamically and does not require stored tokens.


---


# TwoFactor::sendOTPEmail( $user, $otpTokenLifetime, $otp = '' )

## Purpose
Generates and sends a One-Time Password (OTP) via email for Two-Factor Authentication (2FA).

## Process Flow
1. Generate or Validate OTP
    If no OTP is provided, generate a new 4-digit OTP.
    If an OTP is provided, ensure it is exactly 4 digits.
    If the OTP is not 4 digits, throw an error.

2. Revoke Any Unused OTP Tokens
    Remove any existing OTP tokens that are still unused.
    This prevents multiple OTPs from being active at the same time.
    Uses a database update query to mark existing "new" OTPs as "revoked".

3. Encrypt the OTP Token
    The OTP is encrypted using a secret key (linked to the user).
    This ensures that OTPs are stored securely in the database.

4. Store the OTP in the Database
    A new OTP entry is added to the sys_tokens table.
    The record includes:
        User ID
        Email
        Encrypted OTP
        IP Address
        User-Agent (Browser Information)
        Expiration Time (Current Time + OTP Lifetime)
        Status: "new"

5. Send the OTP via Email
    Uses a mailer service to send the OTP to the user's email.
    The email contains:
        Subject: 2FA Token
        Message: "Your 2FA OTP is: [OTP]. This pin will expire in 5 minutes."
        From Address: My Currency Hub
        Bcc: neels@blackonyx.co.za
    Ensures reliable delivery and provides a backup copy via BCC.

6. Return the OTP
    The generated OTP is returned for potential logging or testing.

## Security Considerations
    Revoking unused OTPs prevents multiple valid OTPs from existing simultaneously.
    Encrypting OTPs ensures secure storage.
    Binds OTP to the user's IP and User-Agent to prevent token hijacking.
    Short expiry (5 minutes) minimizes the risk of interception.