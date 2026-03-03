<?php
/**
 * Public Online Voting - Unauthenticated
 * Flow: Category → Gender → Nominee → Votes count → Phone → Confirm → STK Push (same as USSD)
 */
require_once __DIR__ . '/../includes/env.php';
$ussdCode = getenv('USSD_BASE_CODE') ?: '*519*24#';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vote Online - Murang'a 40 Under 40</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --vote-bg: #1a1d24;
            --vote-surface: #23262e;
            --vote-surface-hover: #2c303a;
            --vote-border: #3a3f4b;
            --vote-text: #e8eaed;
            --vote-muted: #9aa0a8;
            --vote-accent: #22c4b8;
            --vote-accent-hover: #2dd9cc;
            --vote-success: #34d399;
            --vote-danger: #f87171;
            --vote-shadow: 0 8px 32px rgba(0,0,0,0.35);
        }
        body {
            font-family: 'DM Sans', -apple-system, sans-serif;
            background: var(--vote-bg);
            color: var(--vote-text);
            min-height: 100vh;
            padding-bottom: 3rem;
        }
        .vote-container { max-width: 440px; margin: 0 auto; padding: 1.5rem 1rem; }
        .vote-hero {
            text-align: center;
            margin-bottom: 1.75rem;
        }
        .vote-hero h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--vote-text);
            letter-spacing: -0.02em;
        }
        .vote-hero p {
            color: var(--vote-muted);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        .step-track {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 0 0.25rem;
        }
        .step-track::before {
            content: '';
            position: absolute;
            left: 1rem;
            right: 1rem;
            top: 1rem;
            height: 2px;
            background: var(--vote-border);
            z-index: 0;
        }
        .step-track { position: relative; }
        .step-badge {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.75rem;
            z-index: 1;
            transition: background 0.2s, color 0.2s;
        }
        .step-active { background: var(--vote-accent); color: var(--vote-bg); }
        .step-done { background: var(--vote-success); color: var(--vote-bg); }
        .step-pending { background: var(--vote-surface); color: var(--vote-muted); border: 1px solid var(--vote-border); }
        .vote-card {
            background: var(--vote-surface);
            border: 1px solid var(--vote-border);
            border-radius: 1rem;
            box-shadow: var(--vote-shadow);
            margin-bottom: 1rem;
        }
        .vote-card .card-body { padding: 1.25rem 1.25rem; }
        .vote-card .card-title {
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--vote-text);
            margin-bottom: 1rem;
        }
        .nominee-btn {
            text-align: left;
            padding: 0.85rem 1rem;
            border-radius: 0.75rem;
            border: 1px solid var(--vote-border);
            background: var(--vote-surface);
            color: var(--vote-text);
            font-weight: 500;
            transition: background 0.2s, border-color 0.2s;
        }
        .nominee-btn:hover {
            background: var(--vote-surface-hover);
            border-color: var(--vote-accent);
            color: var(--vote-accent);
        }
        .btn-vote-primary {
            background: var(--vote-accent);
            color: var(--vote-bg);
            border: none;
            font-weight: 600;
            padding: 0.75rem 1.25rem;
            border-radius: 0.75rem;
        }
        .btn-vote-primary:hover { background: var(--vote-accent-hover); color: var(--vote-bg); }
        .btn-vote-primary:disabled { opacity: 0.7; cursor: not-allowed; }
        .form-control, .form-control:focus {
            background: var(--vote-bg);
            border: 1px solid var(--vote-border);
            color: var(--vote-text);
            border-radius: 0.75rem;
        }
        .form-control::placeholder { color: var(--vote-muted); }
        .form-control:focus { border-color: var(--vote-accent); box-shadow: 0 0 0 3px rgba(34,196,184,0.2); }
        .amount-summary {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--vote-accent);
        }
        .confirm-row { display: flex; justify-content: space-between; padding: 0.4rem 0; font-size: 0.9375rem; }
        .confirm-row span:last-child { color: var(--vote-muted); font-weight: 500; }
        .link-muted { color: var(--vote-muted); font-size: 0.8125rem; text-decoration: none; }
        .link-muted:hover { color: var(--vote-accent); }
        #step-waiting { display: none; }
        .wait-spinner { color: var(--vote-accent); width: 2.5rem; height: 2.5rem; }
        .toast-zone {
            position: fixed;
            top: 1rem;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            pointer-events: none;
        }
        .vote-toast {
            pointer-events: auto;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1.25rem;
            border-radius: 0.75rem;
            background: var(--vote-surface);
            border: 1px solid var(--vote-border);
            box-shadow: var(--vote-shadow);
            min-width: 280px;
            max-width: 90vw;
            animation: toastIn 0.3s ease;
        }
        .vote-toast.toast-error { border-left: 4px solid var(--vote-danger); }
        .vote-toast.toast-success { border-left: 4px solid var(--vote-success); }
        .vote-toast .toast-icon { font-size: 1.25rem; flex-shrink: 0; }
        .vote-toast.toast-error .toast-icon { color: var(--vote-danger); }
        .vote-toast.toast-success .toast-icon { color: var(--vote-success); }
        .vote-toast .toast-msg { font-size: 0.9375rem; color: var(--vote-text); }
        @keyframes toastIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .ussd-banner {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
            background: linear-gradient(135deg, rgba(34, 196, 184, 0.18), rgba(26, 157, 148, 0.12));
            border: 1px solid rgba(34, 196, 184, 0.5);
            border-radius: 0.75rem;
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--vote-accent);
        }
        .ussd-banner .ussd-code {
            font-family: ui-monospace, monospace;
            letter-spacing: 0.05em;
            color: var(--vote-text);
        }
    </style>
</head>
<body>
    <div id="toast-zone" class="toast-zone" aria-live="polite"></div>
    <div class="vote-container">
        <div class="vote-hero">
            <h1>Vote Online</h1>
            <p>Murang'a 40 Under 40 Awards — Pay with M-Pesa, same as USSD</p>
        </div>

        <div class="ussd-banner" role="complementary" aria-label="USSD voting option">
            <i class="bi bi-phone-fill" aria-hidden="true"></i>
            <span>You can also vote via USSD: <strong class="ussd-code"><?php echo htmlspecialchars($ussdCode); ?></strong></span>
        </div>

        <div class="step-track">
            <span class="step-badge step-active" id="s1">1</span>
            <span class="step-badge step-pending" id="s2">2</span>
            <span class="step-badge step-pending" id="s3">3</span>
            <span class="step-badge step-pending" id="s4">4</span>
            <span class="step-badge step-pending" id="s5">5</span>
            <span class="step-badge step-pending" id="s6">6</span>
        </div>

        <!-- Step 1: Categories -->
        <div class="card vote-card mb-3" id="step1">
            <div class="card-body">
                <h5 class="card-title">Select category</h5>
                <div id="categories-list"></div>
                <div id="cat-loading" class="small" style="color: var(--vote-muted);">Loading…</div>
            </div>
        </div>

        <!-- Step 2: Gender -->
        <div class="card vote-card mb-3" id="step2" style="display: none;">
            <div class="card-body">
                <button type="button" class="btn btn-link btn-sm p-0 mb-2 link-muted vote-back" data-prev="1"><i class="bi bi-arrow-left me-1"></i>Back</button>
                <h5 class="card-title">Select gender</h5>
                <div class="d-grid gap-2">
                    <button type="button" class="btn nominee-btn" data-gender="Male"><i class="bi bi-gender-male me-2"></i>Male</button>
                    <button type="button" class="btn nominee-btn" data-gender="Female"><i class="bi bi-gender-female me-2"></i>Female</button>
                </div>
            </div>
        </div>

        <!-- Step 3: Nominees -->
        <div class="card vote-card mb-3" id="step3" style="display: none;">
            <div class="card-body">
                <button type="button" class="btn btn-link btn-sm p-0 mb-2 link-muted vote-back" data-prev="2"><i class="bi bi-arrow-left me-1"></i>Back</button>
                <h5 class="card-title">Select nominee</h5>
                <div id="nominees-list"></div>
                <div id="nom-loading" class="small" style="display: none; color: var(--vote-muted);">Loading…</div>
            </div>
        </div>

        <!-- Step 4: Vote count -->
        <div class="card vote-card mb-3" id="step4" style="display: none;">
            <div class="card-body">
                <button type="button" class="btn btn-link btn-sm p-0 mb-2 link-muted vote-back" data-prev="3"><i class="bi bi-arrow-left me-1"></i>Back</button>
                <h5 class="card-title">Number of votes</h5>
                <p class="small mb-2" style="color: var(--vote-muted);">KES 10 per vote. Enter 1–1000.</p>
                <input type="number" class="form-control form-control-lg mb-3" id="votesCount" min="1" max="1000" value="1" placeholder="Votes">
                <button type="button" class="btn btn-vote-primary w-100" id="btnStep4Next">Next</button>
            </div>
        </div>

        <!-- Step 5: Phone -->
        <div class="card vote-card mb-3" id="step5" style="display: none;">
            <div class="card-body">
                <button type="button" class="btn btn-link btn-sm p-0 mb-2 link-muted vote-back" data-prev="4"><i class="bi bi-arrow-left me-1"></i>Back</button>
                <h5 class="card-title">M-Pesa phone number</h5>
                <input type="tel" class="form-control form-control-lg mb-3" id="phone" placeholder="07XX XXX XXX">
                <button type="button" class="btn btn-vote-primary w-100" id="btnStep5Next">Continue to review</button>
            </div>
        </div>

        <!-- Step 6: Confirm & Pay -->
        <div class="card vote-card mb-3" id="step6" style="display: none;">
            <div class="card-body">
                <button type="button" class="btn btn-link btn-sm p-0 mb-2 link-muted vote-back" data-prev="5"><i class="bi bi-arrow-left me-1"></i>Back</button>
                <h5 class="card-title">Confirm & pay</h5>
                <div class="confirm-row"><span>Nominee</span><span id="confirmNominee"></span></div>
                <div class="confirm-row"><span>Votes</span><span id="confirmVotes"></span></div>
                <div class="confirm-row"><span>Phone</span><span id="confirmPhone"></span></div>
                <div class="confirm-row mt-2 pt-2" style="border-top: 1px solid var(--vote-border);">
                    <span>Total</span>
                    <span class="amount-summary">KES <span id="confirmAmount"></span></span>
                </div>
                <button type="button" class="btn btn-vote-primary btn-lg w-100 mt-3" id="btnPay"><i class="bi bi-phone me-2"></i>Pay with M-Pesa</button>
            </div>
        </div>

        <!-- Waiting for payment -->
        <div class="card vote-card mb-3" id="step-waiting">
            <div class="card-body text-center py-4">
                <div class="spinner-border wait-spinner mb-3" role="status"></div>
                <p class="mb-1 fw-medium" id="waitMessage">Check your phone for M-Pesa STK Push to complete payment.</p>
                <p class="small mt-1" id="waitStatus" style="color: var(--vote-muted);"></p>
            </div>
        </div>

        <p class="text-center mt-4">
            <a href="/admin/" class="link-muted">Admin</a>
        </p>
    </div>

    <script>
        const API = '/api/public-vote-api.php';
        const VOTE_PRICE = 10;
        let state = {
            step: 1,
            categoryId: null,
            categoryName: null,
            gender: null,
            nomineeId: null,
            nomineeName: null,
            votesCount: 1,
            phone: '',
            checkoutRequestId: null
        };

        function setStep(n) {
            state.step = n;
            [1,2,3,4,5,6].forEach(i => {
                const el = document.getElementById('s' + i);
                el.classList.remove('step-active', 'step-done', 'step-pending');
                if (i < n) el.classList.add('step-done');
                else if (i === n) el.classList.add('step-active');
                else el.classList.add('step-pending');
            });
            for (let i = 1; i <= 6; i++) {
                document.getElementById('step' + i).style.display = i === n ? 'block' : 'none';
            }
            document.getElementById('step-waiting').style.display = 'none';
        }

        async function getCategories() {
            const r = await fetch(API + '?action=categories');
            const d = await r.json();
            if (!d.success) throw new Error(d.error || 'Failed to load categories');
            return d.categories;
        }

        async function getNominees(categoryId, gender) {
            const r = await fetch(API + '?action=nominees&category_id=' + categoryId + '&gender=' + encodeURIComponent(gender));
            const d = await r.json();
            if (!d.success) throw new Error(d.error || 'Failed to load nominees');
            return d.nominees;
        }

        async function initiateVote(phone, nomineeId, votesCount) {
            const r = await fetch(API + '?action=initiate-vote', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ phone, nominee_id: nomineeId, votes_count: votesCount })
            });
            return r.json();
        }

        async function getPaymentStatus(checkoutRequestId) {
            const r = await fetch(API + '?action=payment-status&checkout_request_id=' + encodeURIComponent(checkoutRequestId));
            return r.json();
        }

        function showToast(msg, type) {
            type = type || 'error';
            const zone = document.getElementById('toast-zone');
            const el = document.createElement('div');
            el.className = 'vote-toast toast-' + type;
            el.setAttribute('role', 'alert');
            const icon = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill';
            el.innerHTML = '<span class="toast-icon"><i class="bi ' + icon + '"></i></span><span class="toast-msg">' + (msg || (type === 'success' ? 'Done.' : 'Something went wrong. Please try again.')) + '</span>';
            zone.appendChild(el);
            setTimeout(() => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(-6px)';
                setTimeout(() => el.remove(), 300);
            }, 4000);
        }
        function showErr(msg) {
            showToast(msg || 'Something went wrong. Please try again.', 'error');
        }

        // Step 1: categories
        getCategories().then(cats => {
            document.getElementById('cat-loading').style.display = 'none';
            const list = document.getElementById('categories-list');
            list.innerHTML = '';
            (cats || []).forEach(c => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'btn nominee-btn w-100 mb-2';
                b.textContent = c.name;
                b.onclick = () => {
                    state.categoryId = c.id;
                    state.categoryName = c.name;
                    setStep(2);
                };
                list.appendChild(b);
            });
            if (!(cats && cats.length)) list.innerHTML = '<p class="small" style="color: var(--vote-muted);">No categories available.</p>';
        }).catch(e => {
            document.getElementById('cat-loading').textContent = 'Error loading categories.';
            showErr(e.message);
        });

        // Step 2: gender
        document.querySelectorAll('[data-gender]').forEach(btn => {
            btn.addEventListener('click', () => {
                state.gender = btn.getAttribute('data-gender');
                setStep(3);
                document.getElementById('nom-loading').style.display = 'block';
                document.getElementById('nominees-list').innerHTML = '';
                getNominees(state.categoryId, state.gender).then(nominees => {
                    document.getElementById('nom-loading').style.display = 'none';
                    const list = document.getElementById('nominees-list');
                    (nominees || []).forEach(n => {
                        const b = document.createElement('button');
                        b.type = 'button';
                        b.className = 'btn nominee-btn w-100 mb-2';
                        b.textContent = n.name;
                        b.onclick = () => {
                            state.nomineeId = n.id;
                            state.nomineeName = n.name;
                            setStep(4);
                        };
                        list.appendChild(b);
                    });
                    if (!(nominees && nominees.length)) list.innerHTML = '<p class="small" style="color: var(--vote-muted);">No nominees in this category.</p>';
                }).catch(e => {
                    document.getElementById('nom-loading').textContent = 'Error loading nominees.';
                    showErr(e.message);
                });
            });
        });

        // Step 4: votes count -> next
        document.getElementById('votesCount').addEventListener('change', () => {
            const v = parseInt(document.getElementById('votesCount').value, 10);
            if (v >= 1 && v <= 1000) state.votesCount = v;
        });
        document.getElementById('btnStep4Next').addEventListener('click', () => {
            const v = parseInt(document.getElementById('votesCount').value, 10);
            if (isNaN(v) || v < 1 || v > 1000) {
                showErr('Enter a number between 1 and 1000.');
                return;
            }
            state.votesCount = v;
            setStep(5);
        });

        // Step 5: phone -> step 6 (review)
        document.getElementById('btnStep5Next').addEventListener('click', () => {
            const p = document.getElementById('phone').value.trim();
            if (p.length < 9) {
                showErr('Enter a valid M-Pesa phone number.');
                return;
            }
            state.phone = p;
            setStep(6);
            onStep6Show();
        });

        // Step 6: confirm and show summary when step is shown
        function onStep6Show() {
            document.getElementById('confirmNominee').textContent = state.nomineeName;
            document.getElementById('confirmVotes').textContent = state.votesCount + ' votes';
            document.getElementById('confirmPhone').textContent = state.phone;
            document.getElementById('confirmAmount').textContent = (state.votesCount * VOTE_PRICE).toLocaleString();
        }

        document.getElementById('btnPay').addEventListener('click', async () => {
            const phone = document.getElementById('phone').value.trim();
            if (!phone || phone.length < 9) {
                showErr('Enter a valid phone number.');
                return;
            }
            state.phone = phone;
            onStep6Show();
            document.getElementById('btnPay').disabled = true;
            document.getElementById('btnPay').textContent = 'Sending…';
            try {
                const res = await initiateVote(state.phone, state.nomineeId, state.votesCount);
                if (res.success && res.checkout_request_id) {
                    state.checkoutRequestId = res.checkout_request_id;
                    document.getElementById('step6').style.display = 'none';
                    document.getElementById('step-waiting').style.display = 'block';
                    document.getElementById('waitMessage').textContent = res.message || 'Check your phone for M-Pesa STK Push to complete payment.';
                    document.getElementById('waitStatus').textContent = '';
                    let pollCount = 0;
                    const maxPoll = 60;
                    const iv = setInterval(async () => {
                        pollCount++;
                        const st = await getPaymentStatus(state.checkoutRequestId);
                        if (st.status === 'completed') {
                            clearInterval(iv);
                            document.getElementById('waitMessage').textContent = 'Payment successful! Your vote has been recorded.';
                            document.getElementById('waitStatus').textContent = st.receipt ? 'Receipt: ' + st.receipt : '';
                            document.getElementById('waitStatus').style.color = '';
                            showToast('Your vote has been recorded. Thank you!', 'success');
                            setTimeout(() => {
                                state = { step: 1, categoryId: null, categoryName: null, gender: null, nomineeId: null, nomineeName: null, votesCount: 1, phone: '', checkoutRequestId: null };
                                setStep(1);
                                document.getElementById('phone').value = '';
                                document.getElementById('votesCount').value = '1';
                                getCategories().then(cats => {
                                    document.getElementById('cat-loading').style.display = 'none';
                                    const list = document.getElementById('categories-list');
                                    list.innerHTML = '';
                                    (cats || []).forEach(c => {
                                        const b = document.createElement('button');
                                        b.type = 'button';
                                        b.className = 'btn nominee-btn w-100 mb-2';
                                        b.textContent = c.name;
                                        b.onclick = () => { state.categoryId = c.id; state.categoryName = c.name; setStep(2); };
                                        list.appendChild(b);
                                    });
                                });
                            }, 3000);
                        } else if (st.status === 'failed') {
                            clearInterval(iv);
                            document.getElementById('waitMessage').textContent = 'Payment failed.';
                            document.getElementById('waitStatus').textContent = st.message || 'Please try again.';
                            document.getElementById('waitStatus').style.color = 'var(--vote-danger, #e74c3c)';
                            showToast(st.message || 'Payment failed or cancelled. Please try again.', 'error');
                        } else if (pollCount >= maxPoll) {
                            clearInterval(iv);
                            document.getElementById('waitMessage').textContent = 'Taking too long.';
                            document.getElementById('waitStatus').textContent = 'If you completed payment, your vote may still be recorded. You can close this page or start over.';
                            document.getElementById('waitStatus').style.color = 'var(--vote-muted)';
                            showToast('Payment check timed out. If you paid, your vote may still count.', 'error');
                        } else {
                            document.getElementById('waitStatus').textContent = 'Checking payment…';
                        }
                    }, 3000);
                } else {
                    showErr(res.error || 'Could not start payment.');
                    document.getElementById('btnPay').disabled = false;
                    document.getElementById('btnPay').textContent = 'Pay with M-Pesa';
                }
            } catch (e) {
                showErr(e.message || 'Request failed.');
                document.getElementById('btnPay').disabled = false;
                document.getElementById('btnPay').textContent = 'Pay with M-Pesa';
            }
        });

        // When moving to step 6 from step 5 (e.g. button "Next" or Enter), fill summary
        // Back navigation: go to previous step
        document.querySelectorAll('.vote-back').forEach(btn => {
            btn.addEventListener('click', () => {
                const prev = parseInt(btn.getAttribute('data-prev'), 10);
                if (prev >= 1 && prev <= 6) setStep(prev);
            });
        });
    </script>
</body>
</html>
