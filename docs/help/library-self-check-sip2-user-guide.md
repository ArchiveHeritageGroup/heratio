> Heratio Help Center article. Category: Library.

# Self-Check Terminals and Patron PINs (SIP2)

Heratio can talk to self-check kiosks using SIP2, the protocol most library terminals use. A patron identifies themselves at the kiosk, then borrows and returns items without visiting the desk. The kiosk follows the same circulation rules as the desk, including loan limits, fine thresholds and holds.

This feature has been tested by feeding it protocol messages. It has not yet been run over a live network connection or against a real kiosk, so plan a trial with your hardware supplier before putting it in front of patrons.

Setup is done from the server command line by an administrator. There is no staff screen for terminals or PINs.

## What a terminal can do

Heratio answers terminal login, status, resend, patron status, patron information, checkout, checkin, fee paid and block patron messages. It does not support renewals, holds, item information, item status updates, patron enable or end patron session. Some kiosks send an end-session message after every patron and will receive a "please resend" reply, so check this during your trial.

## Patron PINs

Patrons now need a PIN to sign in to their online library account in the OPAC. A patron with no PIN on file is refused and told to ask a librarian. A card number alone never signs anyone in, because it is printed on the card.

There is no staff screen for PINs. An administrator sets one for each patron from the Heratio application directory, running the command as the web server user:

```
php artisan ahg:library-sip2-setup pin <card number>
```

The command prompts for the new PIN, so it never appears in the command history. A PIN is 4 to 12 digits, and running the command again replaces the old one. Plan how you will issue PINs before this feature goes live, since every patron without one will be locked out of the OPAC account until they have one.

The same PIN works at the kiosks and in the OPAC, and failed attempts count across both. After five wrong PINs the card is locked for about 15 minutes. During that time the OPAC says the card is locked, whichever PIN is entered, and does not reveal whether the PIN was right.

## Create a terminal account

Each kiosk logs in with its own account:

```
php artisan ahg:library-sip2-setup account <login> --institution=<id> --branch=<branch id> --user=<staff user id>
```

You will be prompted for a password of at least 12 characters, which cannot contain the `|` character. The login may contain letters, numbers, dots, underscores and hyphens.

Only `--institution` is required. `--branch` sets the branch whose rules the terminal's loans follow, and `--user` sets the staff user recorded on its transactions. Three further options change what the terminal may do:

| Option | Effect |
| --- | --- |
| `--allow-pay` | The terminal may record fee payments. |
| `--allow-block` | The terminal may suspend a patron. |
| `--no-pin` | Patrons may use this terminal without entering a PIN. |

Use `--no-pin` with care. Anyone holding a card could then borrow on it and see the patron's name and balance.

Running the command for an existing login replaces its password and all of these options, so include every option you want to keep. The command cannot switch off a terminal or remove checkout or checkin rights. Those changes currently need an administrator to edit the terminal's database record.

## Start the listener

```
php artisan ahg:library-sip2-serve --host=<server address> --port=6001
```

By default the listener accepts connections only from the server itself, on `127.0.0.1` port 6001. `--host` must be an IP address. You can also set `--max-clients` (default 32) and `--idle`, the seconds before an inactive terminal is disconnected (default 300).

SIP2 sends passwords and PINs as plain text and this listener has no encryption. Keep it on a restricted terminal network or behind an encrypted tunnel, and never expose the port to the internet.

The listener is a long-running process that Heratio does not start for you. Have your system administrator run it under the server's process manager, so it restarts after a reboot or failure.

## Configure the kiosk

Enter the terminal login and password in the kiosk's SIP2 settings, along with the server address and port. The login message must use the plain (unencrypted) user ID and password algorithms. Error detection with sequence numbers and checksums is optional, but if it is on, a wrong checksum causes the message to be rejected.

To check an account without a kiosk, put these two messages in a text file, using your own login and password:

```
9300CN<login>|CO<password>|
9900302.00
```

Then run:

```
php artisan ahg:library-sip2-serve --feed=<path to file>
```

A reply starting `941` means the login worked, and one starting `98` is the status report. Delete the file afterwards, since it holds the password. A feed file is processed for real, so a checkout message in one creates a real loan.

## Fee payments at a terminal

A terminal with `--allow-pay` can record a payment taken by its own payment device. The currency must match the library's **Currency** setting, each payment needs a transaction ID, and it is applied to the patron's fines oldest first. Repeating the same transaction ID does not charge twice.

These payments are kept apart from the cash desk. They reduce the patron's fines, but they create no receipt and do not appear in a cash session or on the end-of-day report, so reconcile them against your payment provider's records.

The patron information reply reports the amount owed but always gives a fine item count of zero and no list of individual fines. Kiosks that display itemised fines will show none.

## Troubleshooting

A patron told they have no PIN needs one set with the `pin` command above. If the OPAC says the card is locked, wait about 15 minutes and try again, since setting a new PIN does not lift the lock early. At a kiosk, "Card or PIN not accepted" covers a locked card, a wrong PIN and a patron who has no PIN at all.

When a terminal cannot log in, check its login and password, and remember that five failed attempts lock that login for about 15 minutes. "Could not bind" means the port is already in use or the address does not belong to this server. If every message gets a `96` reply, the terminal is not logged in, the message type is unsupported, or a checksum is wrong.
