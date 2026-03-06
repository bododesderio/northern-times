-- 0051_login_quotes.sql
-- Stores the rotating quotes shown on the admin login page.

CREATE TABLE IF NOT EXISTS login_quotes (
  id          UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
  quote       TEXT         NOT NULL,
  author      VARCHAR(200) NOT NULL DEFAULT '',
  sort_order  INT          NOT NULL DEFAULT 0,
  is_active   BOOLEAN      NOT NULL DEFAULT TRUE,
  created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_login_quotes_active ON login_quotes(is_active, sort_order);

-- Seed 30 press/journalism quotes
INSERT INTO login_quotes (quote, author, sort_order) VALUES
  ('The press is the best instrument for enlightening the mind of man.', 'Thomas Jefferson', 1),
  ('Journalism is the first rough draft of history.', 'Philip Graham', 2),
  ('A free press can be good or bad, but most certainly without freedom it will never be anything but bad.', 'Albert Camus', 3),
  ('Were it left to me to decide whether we should have a government without newspapers, or newspapers without a government, I should not hesitate to prefer the latter.', 'Thomas Jefferson', 4),
  ('The newspaper is in all its literalness the bible of democracy.', 'Walter Lippmann', 5),
  ('Journalism is what maintains democracy. It is the force for progressive social change.', 'Andrew Vachss', 6),
  ('The job of the newspaper is to comfort the afflicted and afflict the comfortable.', 'Finley Peter Dunne', 7),
  ('All the news that''s fit to print.', 'Adolph Ochs', 8),
  ('A journalist is a person who has nothing to say and knows how to say it.', 'Karl Kraus', 9),
  ('Journalism is literature in a hurry.', 'Matthew Arnold', 10),
  ('The man who reads nothing at all is better educated than the man who reads nothing but newspapers.', 'Thomas Jefferson', 11),
  ('Newspapers are the world''s mirrors.', 'James Ellis', 12),
  ('Freedom of the press is not just important to democracy, it is democracy.', 'Walter Cronkite', 13),
  ('No government ought to be without censors; and where the press is free no government ever will.', 'Thomas Jefferson', 14),
  ('Journalism is the ability to meet the challenge of filling space.', 'Rebecca West', 15),
  ('The role of a great journalist is to hold power to account.', 'Christiane Amanpour', 16),
  ('Get it right, get it fast, get it first — but first, get it right.', 'Unknown', 17),
  ('In journalism, there has always been a tension between getting it first and getting it right.', 'Ellen Goodman', 18),
  ('A good newspaper is never nearly good enough, but a lousy newspaper is a joy forever.', 'Garrison Keillor', 19),
  ('The press must be free; it has always been the guardian of every other freedom.', 'Charles James Fox', 20),
  ('Journalism can never be silent; that is its greatest virtue and its greatest fault.', 'Henry Anatole Grunwald', 21),
  ('Words are, of course, the most powerful drug used by mankind.', 'Rudyard Kipling', 22),
  ('There is no such thing as a little freedom. Either you are all free, or you are not free.', 'Walter Cronkite', 23),
  ('Ink runs from the corners of my mouth. There is no happiness like mine. I have been eating poetry.', 'Mark Strand', 24),
  ('The truth is rarely pure and never simple.', 'Oscar Wilde', 25),
  ('Facts are sacred; comment is free.', 'C.P. Scott', 26),
  ('Every journalist who is not too stupid or too full of himself to notice what is going on knows that what he does is morally indefensible.', 'Janet Malcolm', 27),
  ('Journalism is a mirror that a nation holds up to itself.', 'Murray Kempton', 28),
  ('The public is the only critic whose opinion is worth anything at all.', 'Mark Twain', 29),
  ('Light is the best disinfectant.', 'Louis Brandeis', 30);
