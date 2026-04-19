-- Default formations seed for CoachBoard
-- Import with: mysql -u <user> -p <database> < database/formations.sql
-- Or run via your database client.

-- 4-4-2 diamond (default)
INSERT INTO formation (name, outfield_players, is_default) VALUES ('4-4-2 diamond', 10, 1);
SET @f1 = LAST_INSERT_ID();
INSERT INTO formation_position (formation_id, position_label, line, pos_x, pos_y) VALUES
(@f1, 'Goalkeeper',           'goalkeeper', 50.00,  92.00),
(@f1, 'Right back',           'defence',    80.00,  72.00),
(@f1, 'Centre back right',    'defence',    62.00,  78.00),
(@f1, 'Centre back left',     'defence',    38.00,  78.00),
(@f1, 'Left back',            'defence',    20.00,  72.00),
(@f1, 'Defensive midfielder', 'midfield',   50.00,  60.00),
(@f1, 'Right midfielder',     'midfield',   78.00,  45.00),
(@f1, 'Left midfielder',      'midfield',   22.00,  45.00),
(@f1, 'Attacking midfielder', 'midfield',   50.00,  35.00),
(@f1, 'Striker right',        'attack',     62.00,  18.00),
(@f1, 'Striker left',         'attack',     38.00,  18.00);

-- 4-3-3
INSERT INTO formation (name, outfield_players, is_default) VALUES ('4-3-3', 10, 0);
SET @f2 = LAST_INSERT_ID();
INSERT INTO formation_position (formation_id, position_label, line, pos_x, pos_y) VALUES
(@f2, 'Goalkeeper',        'goalkeeper', 50.00,  92.00),
(@f2, 'Right back',        'defence',    80.00,  72.00),
(@f2, 'Centre back right', 'defence',    62.00,  78.00),
(@f2, 'Centre back left',  'defence',    38.00,  78.00),
(@f2, 'Left back',         'defence',    20.00,  72.00),
(@f2, 'Right midfielder',  'midfield',   70.00,  52.00),
(@f2, 'Centre midfielder', 'midfield',   50.00,  55.00),
(@f2, 'Left midfielder',   'midfield',   30.00,  52.00),
(@f2, 'Right forward',     'attack',     75.00,  20.00),
(@f2, 'Striker',           'attack',     50.00,  15.00),
(@f2, 'Left forward',      'attack',     25.00,  20.00);

-- 4-2-3-1
INSERT INTO formation (name, outfield_players, is_default) VALUES ('4-2-3-1', 10, 0);
SET @f3 = LAST_INSERT_ID();
INSERT INTO formation_position (formation_id, position_label, line, pos_x, pos_y) VALUES
(@f3, 'Goalkeeper',                'goalkeeper', 50.00,  92.00),
(@f3, 'Right back',                'defence',    80.00,  72.00),
(@f3, 'Centre back right',         'defence',    62.00,  78.00),
(@f3, 'Centre back left',          'defence',    38.00,  78.00),
(@f3, 'Left back',                 'defence',    20.00,  72.00),
(@f3, 'Defensive midfielder right','midfield',   62.00,  60.00),
(@f3, 'Defensive midfielder left', 'midfield',   38.00,  60.00),
(@f3, 'Right attacking mid',       'midfield',   75.00,  38.00),
(@f3, 'Central attacking mid',     'midfield',   50.00,  35.00),
(@f3, 'Left attacking mid',        'midfield',   25.00,  38.00),
(@f3, 'Striker',                   'attack',     50.00,  15.00);
