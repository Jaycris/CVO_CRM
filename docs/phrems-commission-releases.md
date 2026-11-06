# PHREMS commission release integration

PHREMS `Sent` (`notified_at`) is treated as **Released**, as agreed by the business. It is slip delivery, not evidence of a bank payment. Only CRM Admin can access release status and history; Finance and agents can see the resulting commission figures without these controls.

## Deployment

Deploy the PHREMS counterpart (reviewed upstream as `79ee1cf`) and this CRM change. Run `php artisan migrate --force` in **each application**, then configure a shared dedicated secret:

- PHREMS: `CRM_COMMISSION_RELEASE_TOKEN`
- CRM: `HRIS_COMMISSION_RELEASE_TOKEN`

The CRM uses its existing HRIS base URL setting (or `HRIS_API_BASE_URL`) and the agent's linked `hris_employee_id`. Run `php artisan optimize:clear` in each application after configuration. Never copy secrets into source, logs, or test fixtures. CRM migrations must run before opening commission pages or recording new disputes.

## Request contract

The CRM POSTs to `/api/crm/commission-release-status` with `Authorization: Bearer <token>`, independently of the employee-directory authentication setting. It does not send `X-HRIS-Token`. The endpoint is read-only; fetching never sends a slip or releases money.

Each request contains `hris_employee_id`, `month` (`Y-m`), and all successful payment shares for that agent and month as `items`. Each item includes `key` (`payment_id:agent_id`), `crm_payment_id`, `crm_agent_id`, `sold_date`, `brand`, `author`, `book_title`, `service`, `payment_method`, and `sale_amount`. Sending all monthly candidates allows PHREMS to detect ambiguous legacy matches. More than 500 items or duplicate payment keys require review.

Responses must identify the same employee and month, specify `complete: true` and `release_basis: "sent"`, and return exactly one result per requested key:

- `released`: requires the sent-slip reference, sent timestamp, and saved `usd_total`, `service_commission`, `markup_commission`, `php_total`, `hold_amount`, `net_commission`, `exchange_rate`, and `card_payment_hold_percent`.
- `unreleased`: a verified absence of a matching sent commission.
- `unknown`: an ambiguous or unverifiable match; never assumed to be unreleased.

The CRM validates the complete response before recording statuses. Authentication errors, timeouts, incomplete results, malformed amounts, and identity mismatches block new dispute deductions. Previously recorded Released status cannot silently become Unreleased; a conflicting result is recorded as Needs review.

## Dispute treatment

New disputes refresh PHREMS status for every commission share, including Frankie shares. Released commissions deduct the **saved sent USD commission** in the current period, with negative balances carried forward. Unreleased commissions cancel their calculated commission and associated hold in the original sale period, without creating a second current-period charge. Sales MTD remains unchanged. Existing dispute entries keep their original treatment; this migration does not backfill or rewrite them.

### Automatic offsets against earlier unpaid commissions

For all agents, new released disputes first use verified Unreleased commissions from earlier months. The CRM refreshes PHREMS before spending that commission and reserves only payable USD after card holds, MTD deductions, earlier period obligations, and previous reservations. Oldest commissions and disputes are handled first. Released and Unknown commissions cannot fund an offset. Main-agent and Frankie balances are separate.

Each reservation creates an audited `commission_dispute_offsets` record. Reports and the commission-slip API include a debit on the funding commission's sold date and an equal USD credit on the dispute's effective date. This lowers the earlier unpaid payout, clears that portion of current debt, and preserves the original dispute deduction. It does not reduce sales MTD, recalculate holds, or create another dispute. Repeated fetches cannot reserve the same money twice. Only the uncovered debt carries forward.

Fetching release status also handles already-recorded disputes whose USD deduction agrees with PHREMS, including legacy entries. As a safeguard against collecting a debt already recovered through payroll, historical deductions are not newly allocated when the agent has sales credit in or after the dispute month: the current PHREMS endpoint verifies sale releases, not debt repayment. Those cases require review. A November dispute with no November sales, such as Malcolm's, is eligible.

If an unpaid funding payment is later disputed, its reservations are voided with a reason and the original uncovered debt returns; other eligible commissions can replace it. If the original dispute is restored after an offset, its original USD is refunded in the restoration period, while preserving the reservation audit. Payments funding active offsets cannot have amount, sold date, or method edited.

Funding commission snapshots preserve their gross basis against later service-price/profile edits. Creating or voiding MTD deductions for a month with active funding reservations is blocked until those settlements have been reviewed, so already committed money cannot be reduced silently.

After deployment run the new migrations. Fetch the source sale month in Commission Releases to process eligible existing disputes. **Recompute affected unsent PHREMS runs before sending them**: saved slips do not update when CRM reports change. For example, an October 28 funding commission's offset appears in the October 25–31 API run, while its November 6 credit cancels the corresponding November debt. Previously sent runs are not rewritten. The PHREMS release endpoint itself requires no new fields for this feature.

### Reconciling an incorrect legacy deduction

Admin can use **Reconcile existing dispute** on a legacy deduction with a uniquely matched PHREMS Released record. The form shows the existing deduction and verified USD, and requires the actual dispute date and a reason. The payment is locked, PHREMS is refreshed, and the existing ledger entry is corrected once. Before/after state, Admin identity, reason, and release evidence are saved in `commission_adjustment_reconciliations`. Payment status and original sale snapshots are preserved. Unknown, Unreleased, restored, and already release-aware entries cannot use this action. Already allocated offsets prevent changing the deduction date or reducing it below the covered amount without a separate review.

Admin can use Agent Statements → Commission Releases → Fetch status from PHREMS to inspect recorded status, references, and history. Unknown results block automatic dispute processing, even if there is an older manual confirmation. No automatic override or fuzzy matching is provided.

## Historical coverage

Only newly computed PHREMS lines receive CRM payment and agent IDs. Existing sent lines use the legacy match on brand, author, book title, service, payment method, sold date, and sale amount. Renamed fields, duplicate candidates, or missing data can produce Unknown. Do not weaken matching or assume older October commissions are unpaid. Admin must reconcile the original sent slip and source identifiers before retrying; a separate explicit reconciliation workflow would be needed to override ambiguous records.

Already computed unsent PHREMS slips are saved results. A later CRM cancellation does not rewrite those saved lines: an authorized payroll operator must review/recompute the unsent run before sending it. Never resend an already sent slip merely to populate IDs.

## Verification

CRM integration tests fake HTTP and use isolated SQLite. They check Bearer authentication, employee/payment identity, complete monthly candidates, saved sent amounts, original-period cancellation, Unknown/error blocking, Admin-only fetching, idempotency, and conflicting status protection. Core commission, date-range, and screen/API parity tests cover financial behavior separately. No live requests, notifications, or payroll actions are performed by these tests.
