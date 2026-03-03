<?php
/**
 * Public Online Voting - Unauthenticated
 * Flow: Category → Gender → Nominee → Votes count → Phone → Confirm → STK Push (same as USSD)
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vote Online - Murang'a 40 Under 40</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root { --vote-primary: #0d6efd; --vote-success: #198754; }
        body { background: #f0f4f8; min-height: 100vh; padding-bottom: 2rem; }
        .vote-card { border: none; border-radius: 1rem; box-shadow: 0 4px 14px rgba(0,0,0,0.08); }
        .step-badge { width: 2rem; height: 2rem; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; }
        .step-active { background: var(--vote-primary); color: #fff; }
        .step-done { background: var(--vote-success); color: #fff; }
        .step-pending { background: #e9ecef; color: #6c757d; }
        .nominee-btn { text-align: left; padding: 0.75rem 1rem; border-radius: 0.5rem; }
        .nominee-btn:hover { background: #e7f1ff; border-color: var(--vote-primary); }
        .amount-summary { font-size: 1.25rem; font-weight: 600; color: var(--vote-primary); }
        #step-waiting { display: none; }
    </style>
</head>
<body>
    <div class="container py-4" style="max-width: 480px;">
        <div class="text-center mb-4">
            <h1 class="h4 mb-1">Vote Online</h1>
            <p class="text-muted small">Murang'a 40 Under 40 Awards — same as USSD, pay via M-Pesa</p>
        </div>

        <!-- Step indicators -->
        <div class="d-flex justify-content-between mb-3 small">
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
                <div id="cat-loading" class="text-muted small">Loading…</div>
            </div>
        </div>

        <!-- Step 2: Gender -->
        <div class="card vote-card mb-3" id="step2" style="display: none;">
            <div class="card-body">
                <h5 class="card-title">Select gender</h5>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-primary nominee-btn" data-gender="Male">Male</button>
                    <button type="button" class="btn btn-outline-primary nominee-btn" data-gender="Female">Female</button>
                </div>
            </div>
        </div>

        <!-- Step 3: Nominees -->
        <div class="card vote-card mb-3" id="step3" style="display: none;">
            <div class="card-body">
                <h5 class="card-title">Select nominee</h5>
                <div id="nominees-list"></div>
                <div id="nom-loading" class="text-muted small" style="display: none;">Loading…</div>
            </div>
        </div>

        <!-- Step 4: Vote count -->
        <div class="card vote-card mb-3" id="step4" style="display: none;">
            <div class="card-body">
                <h5 class="card-title">Number of votes</h5>
                <p class="text-muted small">KES 10 per vote. Enter 1–1000.</p>
                <input type="number" class="form-control form-control-lg mb-2" id="votesCount" min="1" max="1000" value="1" placeholder="Votes">
                <button type="button" class="btn btn-primary w-100" id="btnStep4Next">Next</button>
            </div>
        </div>

        <!-- Step 5: Phone -->
        <div class="card vote-card mb-3" id="step5" style="display: none;">
            <div class="card-body">
                <h5 class="card-title">M-Pesa phone number</h5>
                <input type="tel" class="form-control form-control-lg mb-2" id="phone" placeholder="07XX XXX XXX">
                <button type="button" class="btn btn-primary w-100" id="btnStep5Next">Continue to review</button>
            </div>
        </div>

        <!-- Step 6: Confirm & Pay -->
        <div class="card vote-card mb-3" id="step6" style="display: none;">
            <div class="card-body">
                <button type="button" class="btn btn-link btn-sm p-0 mb-2 text-muted" id="backToPhone">← Change phone</button>
                <h5 class="card-title">Confirm & pay</h5>
                <p class="mb-1"><strong>Nominee:</strong> <span id="confirmNominee"></span></p>
                <p class="mb-1"><strong>Votes:</strong> <span id="confirmVotes"></span></p>
                <p class="mb-1"><strong>Phone:</strong> <span id="confirmPhone"></span></p>
                <p class="amount-summary mb-3">Total: KES <span id="confirmAmount"></span></p>
                <button type="button" class="btn btn-primary btn-lg w-100" id="btnPay">Pay with M-Pesa</button>
            </div>
        </div>

        <!-- Waiting for payment -->
        <div class="card vote-card mb-3" id="step-waiting">
            <div class="card-body text-center py-4">
                <div class="spinner-border text-primary mb-2" role="status"></div>
                <p class="mb-1" id="waitMessage">Check your phone for M-Pesa STK Push to complete payment.</p>
                <p class="small text-muted" id="waitStatus"></p>
            </div>
        </div>

        <p class="text-center small text-muted mt-3">
            <a href="/admin/">Admin</a>
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

        function showErr(msg) {
            alert(msg || 'Something went wrong. Please try again.');
        }

        // Step 1: categories
        getCategories().then(cats => {
            document.getElementById('cat-loading').style.display = 'none';
            const list = document.getElementById('categories-list');
            list.innerHTML = '';
            (cats || []).forEach(c => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'btn btn-outline-primary nominee-btn w-100 mb-2';
                b.textContent = c.name;
                b.onclick = () => {
                    state.categoryId = c.id;
                    state.categoryName = c.name;
                    setStep(2);
                };
                list.appendChild(b);
            });
            if (!(cats && cats.length)) list.innerHTML = '<p class="text-muted">No categories available.</p>';
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
                        b.className = 'btn btn-outline-primary nominee-btn w-100 mb-2';
                        b.textContent = n.name + (n.votes_count != null ? ' (' + Number(n.votes_count).toLocaleString() + ' votes)' : '');
                        b.onclick = () => {
                            state.nomineeId = n.id;
                            state.nomineeName = n.name;
                            setStep(4);
                        };
                        list.appendChild(b);
                    });
                    if (!(nominees && nominees.length)) list.innerHTML = '<p class="text-muted">No nominees in this category.</p>';
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
            document.getElementById('confirmVotes').textContent = state.votesCount;
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
                        document.getElementById('waitStatus').textContent = 'Checking payment…';
                        if (st.status === 'completed') {
                            clearInterval(iv);
                            document.getElementById('waitMessage').textContent = 'Payment successful! Your vote has been recorded.';
                            document.getElementById('waitStatus').textContent = st.receipt ? 'Receipt: ' + st.receipt : '';
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
                                        b.className = 'btn btn-outline-primary nominee-btn w-100 mb-2';
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
                        }
                        if (pollCount >= maxPoll) clearInterval(iv);
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
        document.getElementById('backToPhone').addEventListener('click', () => setStep(5));
    </script>
</body>
</html>
