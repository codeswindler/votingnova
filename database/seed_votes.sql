-- Seed data for Votes and Transactions (Simulation Data)
-- This data is for local testing/demo purposes only
-- DROP ALL DATA before going live with real votes

USE votingnova;

-- Clear existing simulation data (optional - comment out if you want to keep existing data)
-- DELETE FROM votes WHERE phone LIKE '2547%' OR phone LIKE '2541%';
-- DELETE FROM mpesa_transactions WHERE checkout_request_id LIKE 'SIM-%';

-- Generate sample votes across different categories and nominees
-- This creates realistic voting patterns for dashboard visualization

-- Get some nominees from each category
SET @innovation_male = (SELECT id FROM nominees WHERE category_id = 1 AND gender = 'Male' LIMIT 1);
SET @innovation_female = (SELECT id FROM nominees WHERE category_id = 1 AND gender = 'Female' LIMIT 1);
SET @media_male = (SELECT id FROM nominees WHERE category_id = 2 AND gender = 'Male' LIMIT 1);
SET @media_female = (SELECT id FROM nominees WHERE category_id = 2 AND gender = 'Female' LIMIT 1);
SET @education_male = (SELECT id FROM nominees WHERE category_id = 3 AND gender = 'Male' LIMIT 1);
SET @education_female = (SELECT id FROM nominees WHERE category_id = 3 AND gender = 'Female' LIMIT 1);
SET @business_male = (SELECT id FROM nominees WHERE category_id = 6 AND gender = 'Male' LIMIT 1);
SET @business_female = (SELECT id FROM nominees WHERE category_id = 6 AND gender = 'Female' LIMIT 1);
SET @music_male = (SELECT id FROM nominees WHERE category_id = 11 AND gender = 'Male' LIMIT 1);
SET @music_female = (SELECT id FROM nominees WHERE category_id = 11 AND gender = 'Female' LIMIT 1);

-- Insert sample votes with transactions
-- Innovation category votes
INSERT INTO votes (nominee_id, phone, votes_count, amount, status, mpesa_ref, transaction_id, created_at) VALUES
(@innovation_male, '254712345001', 5, 50.00, 'completed', 'SIM001ABC', 'SIM-INNOV-001', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(@innovation_female, '254712345002', 10, 100.00, 'completed', 'SIM002DEF', 'SIM-INNOV-002', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(@innovation_male, '254712345003', 3, 30.00, 'completed', 'SIM003GHI', 'SIM-INNOV-003', DATE_SUB(NOW(), INTERVAL 12 HOUR)),
(@innovation_female, '254712345004', 7, 70.00, 'completed', 'SIM004JKL', 'SIM-INNOV-004', DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(@innovation_male, '254712345005', 15, 150.00, 'completed', 'SIM005MNO', 'SIM-INNOV-005', DATE_SUB(NOW(), INTERVAL 3 HOUR));

-- Media category votes
INSERT INTO votes (nominee_id, phone, votes_count, amount, status, mpesa_ref, transaction_id, created_at) VALUES
(@media_male, '254712345006', 8, 80.00, 'completed', 'SIM006PQR', 'SIM-MEDIA-001', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(@media_female, '254712345007', 12, 120.00, 'completed', 'SIM007STU', 'SIM-MEDIA-002', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(@media_male, '254712345008', 4, 40.00, 'completed', 'SIM008VWX', 'SIM-MEDIA-003', DATE_SUB(NOW(), INTERVAL 10 HOUR)),
(@media_female, '254712345009', 20, 200.00, 'completed', 'SIM009YZA', 'SIM-MEDIA-004', DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(@media_male, '254712345010', 6, 60.00, 'completed', 'SIM010BCD', 'SIM-MEDIA-005', DATE_SUB(NOW(), INTERVAL 2 HOUR));

-- Education category votes
INSERT INTO votes (nominee_id, phone, votes_count, amount, status, mpesa_ref, transaction_id, created_at) VALUES
(@education_male, '254712345011', 9, 90.00, 'completed', 'SIM011EFG', 'SIM-EDUC-001', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(@education_female, '254712345012', 11, 110.00, 'completed', 'SIM012HIJ', 'SIM-EDUC-002', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(@education_male, '254712345013', 5, 50.00, 'completed', 'SIM013KLM', 'SIM-EDUC-003', DATE_SUB(NOW(), INTERVAL 8 HOUR)),
(@education_female, '254712345014', 18, 180.00, 'completed', 'SIM014NOP', 'SIM-EDUC-004', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(@education_male, '254712345015', 7, 70.00, 'completed', 'SIM015QRS', 'SIM-EDUC-005', DATE_SUB(NOW(), INTERVAL 1 HOUR));

-- Business category votes
INSERT INTO votes (nominee_id, phone, votes_count, amount, status, mpesa_ref, transaction_id, created_at) VALUES
(@business_male, '254712345016', 25, 250.00, 'completed', 'SIM016TUV', 'SIM-BUSI-001', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(@business_female, '254712345017', 30, 300.00, 'completed', 'SIM017WXY', 'SIM-BUSI-002', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(@business_male, '254712345018', 10, 100.00, 'completed', 'SIM018ZAB', 'SIM-BUSI-003', DATE_SUB(NOW(), INTERVAL 9 HOUR)),
(@business_female, '254712345019', 15, 150.00, 'completed', 'SIM019CDE', 'SIM-BUSI-004', DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(@business_male, '254712345020', 22, 220.00, 'completed', 'SIM020FGH', 'SIM-BUSI-005', DATE_SUB(NOW(), INTERVAL 2 HOUR));

-- Music category votes
INSERT INTO votes (nominee_id, phone, votes_count, amount, status, mpesa_ref, transaction_id, created_at) VALUES
(@music_male, '254712345021', 6, 60.00, 'completed', 'SIM021IJK', 'SIM-MUSI-001', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(@music_female, '254712345022', 14, 140.00, 'completed', 'SIM022LMN', 'SIM-MUSI-002', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(@music_male, '254712345023', 8, 80.00, 'completed', 'SIM023OPQ', 'SIM-MUSI-003', DATE_SUB(NOW(), INTERVAL 7 HOUR)),
(@music_female, '254712345024', 16, 160.00, 'completed', 'SIM024RST', 'SIM-MUSI-004', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(@music_male, '254712345025', 12, 120.00, 'completed', 'SIM025UVW', 'SIM-MUSI-005', DATE_SUB(NOW(), INTERVAL 1 HOUR));

-- Insert corresponding M-Pesa transactions
INSERT INTO mpesa_transactions (phone, amount, checkout_request_id, merchant_request_id, status, mpesa_receipt_number, result_code, result_desc, created_at) VALUES
('254712345001', 50.00, 'SIM-INNOV-001', 'SIM-MERCH-001', 'completed', 'SIM001ABC', 0, 'The service request is processed successfully.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
('254712345002', 100.00, 'SIM-INNOV-002', 'SIM-MERCH-002', 'completed', 'SIM002DEF', 0, 'The service request is processed successfully.', DATE_SUB(NOW(), INTERVAL 1 DAY)),
('254712345003', 30.00, 'SIM-INNOV-003', 'SIM-MERCH-003', 'completed', 'SIM003GHI', 0, 'The service request is processed successfully.', DATE_SUB(NOW(), INTERVAL 12 HOUR)),
('254712345004', 70.00, 'SIM-INNOV-004', 'SIM-MERCH-004', 'completed', 'SIM004JKL', 0, 'The service request is processed successfully.', DATE_SUB(NOW(), INTERVAL 6 HOUR)),
('254712345005', 150.00, 'SIM-INNOV-005', 'SIM-MERCH-005', 'completed', 'SIM005MNO', 0, 'The service request is processed successfully.', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
('254712345006', 80.00, 'SIM-MEDIA-001', 'SIM-MERCH-006', 'completed', 'SIM006PQR', 0, 'The service request is processed successfully.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
('254712345007', 120.00, 'SIM-MEDIA-002', 'SIM-MERCH-007', 'completed', 'SIM007STU', 0, 'The service request is processed successfully.', DATE_SUB(NOW(), INTERVAL 1 DAY)),
('254712345008', 40.00, 'SIM-MEDIA-003', 'SIM-MERCH-008', 'completed', 'SIM008VWX', 0, 'The service request is processed successfully.', DATE_SUB(NOW(), INTERVAL 10 HOUR)),
('254712345009', 200.00, 'SIM-MEDIA-004', 'SIM-MERCH-009', 'completed', 'SIM009YZA', 0, 'The service request is processed successfully.', DATE_SUB(NOW(), INTERVAL 5 HOUR)),
('254712345010', 60.00, 'SIM-MEDIA-005', 'SIM-MERCH-010', 'completed', 'SIM010BCD', 0, 'The service request is processed successfully.', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('254712345011', 90.00, 'SIM-EDUC-001', 'SIM-MERCH-011', 'completed', 'SIM011EFG', 0, 'The service request is processed successfully.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
('254712345012', 110.00, 'SIM-EDUC-002', 'SIM-MERCH-012', 'completed', 'SIM012HIJ', 0, 'The service request is processed successfully.', DATE_SUB(NOW(), INTERVAL 1 DAY)),
('254712345013', 50.00, 'SIM-EDUC-003', 'SIM-MERCH-013', 'completed', 'SIM013KLM', 0, 'The service request is processed successfully.', DATE_SUB(NOW(), INTERVAL 8 HOUR)),
('254712345014', 180.00, 'SIM-EDUC-004', 'SIM-MERCH-014', 'completed', 'SIM014NOP', 0, 'The service request is processed successfully.', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
('254712345015', 70.00, 'SIM-EDUC-005', 'SIM-MERCH-015', 'completed', 'SIM015QRS', 0, 'The service request is processed successfully.', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
('254712345016', 250.00, 'SIM-BUSI-001', 'SIM-MERCH-016', 'completed', 'SIM016TUV', 0, 'The service request is processed successfully.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
('254712345017', 300.00, 'SIM-BUSI-002', 'SIM-MERCH-017', 'completed', 'SIM017WXY', 0, 'The service request is processed successfully.', DATE_SUB(NOW(), INTERVAL 1 DAY)),
('254712345018', 100.00, 'SIM-BUSI-003', 'SIM-MERCH-018', 'completed', 'SIM018ZAB', 0, 'The service request is processed successfully.', DATE_SUB(NOW(), INTERVAL 9 HOUR)),
('254712345019', 150.00, 'SIM-BUSI-004', 'SIM-MERCH-019', 'completed', 'SIM019CDE', 0, 'The service request is processed successfully.', DATE_SUB(NOW(), INTERVAL 5 HOUR)),
('254712345020', 220.00, 'SIM-BUSI-005', 'SIM-MERCH-020', 'completed', 'SIM020FGH', 0, 'The service request is processed successfully.', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('254712345021', 60.00, 'SIM-MUSI-001', 'SIM-MERCH-021', 'completed', 'SIM021IJK', 0, 'The service request is processed successfully.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
('254712345022', 140.00, 'SIM-MUSI-002', 'SIM-MERCH-022', 'completed', 'SIM022LMN', 0, 'The service request is processed successfully.', DATE_SUB(NOW(), INTERVAL 1 DAY)),
('254712345023', 80.00, 'SIM-MUSI-003', 'SIM-MERCH-023', 'completed', 'SIM023OPQ', 0, 'The service request is processed successfully.', DATE_SUB(NOW(), INTERVAL 7 HOUR)),
('254712345024', 160.00, 'SIM-MUSI-004', 'SIM-MERCH-024', 'completed', 'SIM024RST', 0, 'The service request is processed successfully.', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
('254712345025', 120.00, 'SIM-MUSI-005', 'SIM-MERCH-025', 'completed', 'SIM025UVW', 0, 'The service request is processed successfully.', DATE_SUB(NOW(), INTERVAL 1 HOUR));

-- Update nominee vote counts based on the votes
UPDATE nominees n
INNER JOIN (
    SELECT nominee_id, SUM(votes_count) as total_votes
    FROM votes
    WHERE status = 'completed'
    GROUP BY nominee_id
) v ON n.id = v.nominee_id
SET n.votes_count = v.total_votes;
