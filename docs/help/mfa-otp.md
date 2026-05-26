# Email and SMS two-factor codes

If you do not want to use an authenticator app or a hardware passkey,
you can sign in with a 6-digit code delivered by email or SMS.

## Adding an email or SMS factor

1. Sign in and open **Profile > Security**.
2. Click **Email and SMS factors**, then **Add factor**.
3. Pick **Email** or **SMS** and enter the destination address or phone
   number. For SMS, use international format with a `+` prefix
   (for example `+27821234567`).
4. Give the factor a label (e.g. "Work email", "Cellphone") so you can
   tell it apart from any other factors you enrol later.
5. We send a 6-digit code to that destination. Enter it on the next
   screen to confirm you own the address or number.

The factor is marked **Verified** as soon as you enter the first code
correctly. You can enrol as many email and SMS destinations as you
like.

## Signing in with an email or SMS code

1. Enter your email and password as usual.
2. If you have an authenticator app or a passkey enrolled as well,
   pick **Email or SMS code** from the chooser.
3. Pick the destination you want the code sent to, then click
   **Send code**.
4. Open the email or SMS and type the 6 digits into the **Verification
   code** box.
5. The code expires in 10 minutes. After five wrong attempts the factor
   is temporarily locked for 15 minutes - try a different factor or
   wait and try again.

## Removing a factor

Open **Profile > Security > Email and SMS factors**, find the row you
want to remove, and click **Delete**. The factor is gone immediately;
you cannot sign in with it any more.

## Frequently asked questions

**Can I use the same address for multiple accounts?** Yes. Each user
manages their own factors; there is no cross-user check.

**What happens if I lose access to my email or phone?** Sign in with a
different factor if you have one, or contact your administrator to
remove the factor from your account.

**Are the codes safe to share?** No - a 6-digit code is a one-time
key. Never share it. Heratio will never ask you to read your code out
to a support agent.

**Why am I asked to wait between resends?** We rate-limit code
delivery to one code per 60 seconds per factor so an attacker cannot
flood your inbox or burn SMS credit.
