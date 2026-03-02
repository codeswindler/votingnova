-- Seed data for USSD Voting System
-- Categories and Nominees from screenshots - PROPER CAPITALIZATION

USE votingnova;

-- Insert Categories
INSERT INTO categories (name) VALUES
('Innovation'),
('Media'),
('Education'),
('Religion'),
('Sport'),
('Business'),
('Community Service'),
('Agriculture'),
('Youth Advocacy'),
('Leadership'),
('Music'),
('Technology')
ON DUPLICATE KEY UPDATE name=name;

-- Insert Male Nominees (Proper capitalization)
-- Innovation - Male
INSERT INTO nominees (category_id, name, gender) VALUES
(1, 'Meshack Kipkorir', 'Male'),
(1, 'Stephen Kimani', 'Male'),
(1, 'John Magiro', 'Male'),
(1, 'Richard Maina', 'Male'),
(1, 'Winter Kimani Wanja', 'Male'),
(1, 'Engineer Winter Kimani', 'Male'),
(1, 'Mike Steve', 'Male'),
(1, 'Shujaa Humphrey', 'Male'),
(1, 'Mkulima Mdogo', 'Male'),
(1, 'Jamaica Mwangi', 'Male'),
(1, 'Leafylife', 'Male'),
(1, 'David Kamau', 'Male'),
(1, 'Thuku Kanyoro', 'Male'),
(1, 'Ephantus Waiharo', 'Male'),
(1, 'Victor Kinuthia', 'Male'),
(1, 'Githuki Wa Nyokabi', 'Male'),
(1, 'Benson Wang''ombe', 'Male');

-- Media - Male
INSERT INTO nominees (category_id, name, gender) VALUES
(2, 'Victor Kinuthia', 'Male'),
(2, 'Victor Mwaura Ndung''u', 'Male'),
(2, 'Alfred Maina', 'Male'),
(2, 'Kelvin Kamau', 'Male'),
(2, 'Dennis Citizen TV', 'Male'),
(2, 'Joseph Mburu', 'Male'),
(2, 'Gachie Wangechi', 'Male'),
(2, 'Anthony Gathanju', 'Male'),
(2, 'Wambui Mwangi', 'Male'),
(2, 'Eutychus Ngechu', 'Male'),
(2, 'Kim Winters', 'Male'),
(2, 'Exam Maina', 'Male'),
(2, 'Ng''ethe Steve', 'Male'),
(2, 'Alex Waweru', 'Male'),
(2, 'Githuki Wa Nyokabi', 'Male'),
(2, 'Benson Wang''ombe', 'Male');

-- Education - Male
INSERT INTO nominees (category_id, name, gender) VALUES
(3, 'Alex Kiru Boys', 'Male'),
(3, 'Dr. Kamau Wairuri', 'Male'),
(3, 'Oscar Mutembei', 'Male'),
(3, 'Njathi Mwinga', 'Male'),
(3, 'Eliud Karobia', 'Male'),
(3, 'Kevin Muniu', 'Male'),
(3, 'Dr. Gatogo', 'Male'),
(3, 'Salim Mwiti', 'Male'),
(3, 'Daniel Ruga', 'Male'),
(3, 'Livingstone Mwaura', 'Male'),
(3, 'Canon Gichigo', 'Male'),
(3, 'Peter Maina', 'Male'),
(3, 'Paul Wachira Muraguri', 'Male');

-- Religion - Male
INSERT INTO nominees (category_id, name, gender) VALUES
(4, 'Elijah Mwangi Wanjiku', 'Male'),
(4, 'Pastor Benson Gachihi', 'Male'),
(4, 'Jamaica Mwangi', 'Male'),
(4, 'Brian Wanjohi', 'Male'),
(4, 'Pst. Mavi', 'Male'),
(4, 'Kamonde', 'Male'),
(4, 'Mugo Morris', 'Male'),
(4, 'Pst. Jimmy', 'Male'),
(4, 'Pst. Dan', 'Male'),
(4, 'Joseph Kamau', 'Male'),
(4, 'Canon Gichigo', 'Male'),
(4, 'Benson Muiga', 'Male'),
(4, 'Oscar Muiruri', 'Male'),
(4, 'Paul Wachira Muraguri', 'Male'),
(4, 'Solomon Kamau', 'Male');

-- Sport - Male
INSERT INTO nominees (category_id, name, gender) VALUES
(5, 'Joe Waithira', 'Male'),
(5, 'Kelvin Njuma', 'Male'),
(5, 'Kunga', 'Male'),
(5, 'Karuma', 'Male'),
(5, 'Titus Kamau', 'Male'),
(5, 'Joe Waithira', 'Male'),
(5, 'Simon Olelenku', 'Male'),
(5, 'Hon. Anthony Marubu', 'Male'),
(5, 'James Kinuthia', 'Male'),
(5, 'Joel Mseal', 'Male'),
(5, 'Joseph Wachira', 'Male'),
(5, 'Antony Ngamini', 'Male'),
(5, 'Ken Maina Mwangi', 'Male'),
(5, 'Solomon Kamau', 'Male');

-- Business - Male
INSERT INTO nominees (category_id, name, gender) VALUES
(6, 'George Gathuru', 'Male'),
(6, 'Arthur Kimotho', 'Male'),
(6, 'Munga Murarandia MCA', 'Male'),
(6, 'Michael Mwangi', 'Male'),
(6, 'Maina Joseph', 'Male'),
(6, 'Ben Kiama', 'Male'),
(6, 'Doc Dan', 'Male'),
(6, 'Dennis Musyoka', 'Male'),
(6, 'Jamaica Mwangi', 'Male'),
(6, 'Ignatius Mwaura', 'Male'),
(6, 'James Macharia', 'Male'),
(6, 'Andrew Karanja', 'Male');

-- Community Service - Male
INSERT INTO nominees (category_id, name, gender) VALUES
(7, 'Muchoki Mbuthia', 'Male'),
(7, 'Eutychus Ngechu', 'Male'),
(7, 'Ndindi Nyoro', 'Male'),
(7, 'Kagiri Kimani', 'Male'),
(7, 'Paul Otieno', 'Male'),
(7, 'Kimani', 'Male'),
(7, 'Kamau Wairuri', 'Male'),
(7, 'Joseph Ngugi', 'Male'),
(7, 'Ruiru Miako', 'Male'),
(7, 'Victor Mwaura Ndung''u', 'Male'),
(7, 'James Macharia', 'Male'),
(7, 'Paul Wachira Muraguri', 'Male');

-- Agriculture - Male
INSERT INTO nominees (category_id, name, gender) VALUES
(8, 'Josphat Kang''ethe', 'Male'),
(8, 'Joseph Ndung''u (Joyen Seedlings)', 'Male'),
(8, 'Mkulima Mdogo', 'Male'),
(8, 'Peter Kuria', 'Male'),
(8, 'Ian Ndungu', 'Male'),
(8, 'Herman Kimondo', 'Male'),
(8, 'Lemila', 'Male'),
(8, 'Johija Farm', 'Male'),
(8, 'Hilary Swank', 'Male'),
(8, 'Devis Bahati', 'Male'),
(8, 'Paul Wachira Muraguri', 'Male');

-- Youth Advocacy - Male
INSERT INTO nominees (category_id, name, gender) VALUES
(9, 'Manoah Gachucha', 'Male'),
(9, 'Laban Thua Njeri', 'Male'),
(9, 'Zack Kinuthia', 'Male'),
(9, 'David Murigi', 'Male'),
(9, 'Evan Mbathi', 'Male'),
(9, 'Joseph Kibugi', 'Male'),
(9, 'Stephen Munania', 'Male');

-- Leadership - Male
INSERT INTO nominees (category_id, name, gender) VALUES
(10, 'Ndindi Nyoro', 'Male'),
(10, 'Joseph Kibugi', 'Male'),
(10, 'Kang''ata', 'Male'),
(10, 'Paulo Ndung''u', 'Male'),
(10, 'Ruth Kerubo', 'Male'),
(10, 'Heman Kimondo', 'Male');

-- Music - Male
INSERT INTO nominees (category_id, name, gender) VALUES
(11, 'Ngethe Steve', 'Male'),
(11, 'Gathee Wa Njeri', 'Male'),
(11, 'Lewis Gitahi', 'Male'),
(11, 'Rodgers Mwangi', 'Male'),
(11, 'Kamande Samuel', 'Male'),
(11, 'Denis Irungu', 'Male'),
(11, 'Euphrates Chege', 'Male');

-- Technology - Male
INSERT INTO nominees (category_id, name, gender) VALUES
(12, 'James Gatebe', 'Male'),
(12, 'Eutychus Ngechu', 'Male'),
(12, 'Jackson Wanjane', 'Male'),
(12, 'Heman Kimondo', 'Male');

-- Insert Female Nominees (Proper capitalization)
-- Innovation - Female
INSERT INTO nominees (category_id, name, gender) VALUES
(1, 'Beulah Muthoni', 'Female'),
(1, 'Miriam Wangeci', 'Female'),
(1, 'Rachel Wamucii', 'Female'),
(1, 'Annette Ngunjiri', 'Female'),
(1, 'Marion Njeri Wachira', 'Female'),
(1, 'Jackline Wanjohi', 'Female');

-- Media - Female
INSERT INTO nominees (category_id, name, gender) VALUES
(2, 'Wangechi Gachie', 'Female'),
(2, 'Buthy Waziri', 'Female'),
(2, 'Boss Baby', 'Female'),
(2, 'Mugo Morris', 'Female'),
(2, 'Wanjiru Wa Waya', 'Female'),
(2, 'Wambui Mwangi', 'Female'),
(2, 'Jackline Wanjohi', 'Female');

-- Education - Female
INSERT INTO nominees (category_id, name, gender) VALUES
(3, 'Winnie Wanjiku', 'Female'),
(3, 'Mercy Maina', 'Female'),
(3, 'Damaris Wambui Muraguri', 'Female');

-- Religion - Female
INSERT INTO nominees (category_id, name, gender) VALUES
(4, 'Catherine Mbuthia', 'Female'),
(4, 'Esther Njeri', 'Female'),
(4, 'Lucy Kinyua', 'Female'),
(4, 'Cristina', 'Female'),
(4, 'Mary Juma', 'Female'),
(4, 'Hellen Kimathi', 'Female'),
(4, 'Edith Wambui', 'Female'),
(4, 'Susan Kimani', 'Female'),
(4, 'Jedidah Karehu', 'Female');

-- Sport - Female
INSERT INTO nominees (category_id, name, gender) VALUES
(5, 'Liz Mbugua', 'Female'),
(5, 'Valencia', 'Female'),
(5, 'Elizabeth Wanjiru Ndegwa', 'Female'),
(5, 'Maxwell Maina', 'Female');

-- Business - Female
INSERT INTO nominees (category_id, name, gender) VALUES
(6, 'Sarafina Muthoni', 'Female'),
(6, 'Dama Spares', 'Female'),
(6, 'Margaret Gathogo', 'Female'),
(6, 'Jane Kamau', 'Female'),
(6, 'Pamela Walthera', 'Female'),
(6, 'Lucy Wamai', 'Female'),
(6, 'Wambui Kariuki', 'Female'),
(6, 'Esther Joy', 'Female'),
(6, 'Joyce Kahiga', 'Female'),
(6, 'Winnie Mumba', 'Female'),
(6, 'Betty Wanjiru Muchonjo', 'Female');

-- Community Service - Female
INSERT INTO nominees (category_id, name, gender) VALUES
(7, 'Miriam Karanja', 'Female'),
(7, 'Angel', 'Female'),
(7, 'Wambui Nyutu', 'Female'),
(7, 'Beulah', 'Female'),
(7, 'Wairimu Nganga', 'Female'),
(7, 'Hon. Diana Muthoni', 'Female'),
(7, 'Betty Namasa', 'Female'),
(7, 'Aurelia Wacera', 'Female'),
(7, 'Sylvia Wangui', 'Female'),
(7, 'Esther Madam Governor', 'Female'),
(7, 'Margaret Wanjiru Muraguri', 'Female'),
(7, 'Mirriam Karanja', 'Female'),
(7, 'Elizabeth Ndegwa', 'Female'),
(7, 'Pauline Nyambura', 'Female');

-- Agriculture - Female
INSERT INTO nominees (category_id, name, gender) VALUES
(8, 'Clowers Shemaiah', 'Female');

-- Youth Advocacy - Female
INSERT INTO nominees (category_id, name, gender) VALUES
(9, 'Hon. Beulah Muthoni', 'Female'),
(9, 'Joseline Ngugi', 'Female'),
(9, 'Njeri Maina', 'Female'),
(9, 'Judy Nyambura Waiguru', 'Female'),
(9, 'Ann Nyamu', 'Female'),
(9, 'Kindness Muthoni', 'Female'),
(9, 'Agnes Muchoki', 'Female');

-- Leadership - Female
INSERT INTO nominees (category_id, name, gender) VALUES
(10, 'Hon. Diana Muthoni', 'Female'),
(10, 'Hon. City', 'Female'),
(10, 'Wambui Nyutu', 'Female'),
(10, 'Esther Madam Governor', 'Female'),
(10, 'Margaret Wanjiru Muraguri', 'Female'),
(10, 'Mirriam Karanja', 'Female'),
(10, 'Elizabeth Ndegwa', 'Female'),
(10, 'Pauline Nyambura', 'Female');

-- Music - Female
INSERT INTO nominees (category_id, name, gender) VALUES
(11, 'Joy Wa Macharia', 'Female'),
(11, 'Stella', 'Female'),
(11, 'Perez', 'Female'),
(11, 'MC Uzziah', 'Female'),
(11, 'Gichure Wa Beth', 'Female');

-- Technology - Female
INSERT INTO nominees (category_id, name, gender) VALUES
(12, 'Favor Nduta', 'Female');

-- Insert default admin user (password: admin123 - should be changed in production)
-- Password hash for 'admin123' using password_hash()
INSERT INTO admin_users (username, password_hash, email, full_name) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@votingnova.com', 'System Administrator')
ON DUPLICATE KEY UPDATE username=username;
