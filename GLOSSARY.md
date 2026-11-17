# PixelForge Image Gen

An app where signed-in users generate images through the Pixelforge provider and are charged for each one.

## Generations

**Generation**:
One request by a user for one image, from the moment they submit it until it succeeds or fails.
_Avoid_: Job, request, image (when meaning the whole request)

**Waiting for a slot**:
The status of a generation we've saved but Pixelforge hasn't accepted yet (waiting for a free slot or retrying after a "busy" reply).
_Avoid_: Pending, in line, queued (Pixelforge's own status)

## Money

**Provider cost**:
What Pixelforge bills us for one generation, worked out from its token usage and the rates fixed when it was created.
_Avoid_: Cost (unqualified), Pixelforge price

**Charge**:
What the user pays for one generation: the provider cost plus the markup, rounded up to the nearest nanodollar.
_Avoid_: Price, fee, user cost

**Markup**:
The 25% added on top of the provider cost; our profit on a generation.
_Avoid_: Margin, commission

**Estimate**:
The range (low to high) the charge will fall within, shown before a generation is submitted and kept on it afterwards.
_Avoid_: Quote, price preview

**Actual charge**:
The charge recorded once a generation succeeds, from its real token usage; never above the top of its estimate.
_Avoid_: Final price, real cost

**Capped charge**:
An actual charge limited to the top of its estimate because Pixelforge's rates rose between the estimate and acceptance; we absorb the difference.
_Avoid_: Discount, adjustment

**Balance**:
The credits a user holds, in US dollars; reduced only when a generation succeeds (by its actual charge) and increased by the sign-up bonus or buying credits.
_Avoid_: Wallet, funds, account

**Ledger**:
A user's running list of every credit movement; their balance is its total.
_Avoid_: Transaction log, history (that's the generations list)

**Ledger entry**:
One line in the ledger with an amount, a reason and a time. The reasons are the sign-up bonus, a credit purchase, or a generation's actual charge.
_Avoid_: Transaction, movement

**Credit pack**:
A fixed amount of credits ($5, $10 or $25) a user can buy with one click; a fake purchase.
_Avoid_: Top-up, plan, bundle

**Available balance**:
The balance minus the top of the estimate for each of the user's generations still in progress; what they can start right now.
_Avoid_: Hold, reserved, spendable

**Nanodollar**:
One billionth of a US dollar; the smallest unit any amount is recorded in.
