import javax.swing.*;
import javax.swing.border.EmptyBorder;
import java.awt.*;
import java.time.LocalDate;
import java.time.temporal.ChronoUnit;
import java.util.ArrayList;
import java.util.Comparator;
import java.util.HashSet;
import java.util.List;
import java.util.Locale;
import java.util.Set;

public class Main {
    private final List<Job> jobs = sampleJobs();
    private final JPanel resultsPanel = new JPanel();
    private final JLabel summaryLabel = new JLabel();

    private final JTextField strandField = new JTextField(14);
    private final JTextField locationField = new JTextField(14);
    private final JTextField skillsField = new JTextField(24);

    public static void main(String[] args) {
        SwingUtilities.invokeLater(new Runnable() {
            @Override
            public void run() {
                new Main().start();
            }
        });
    }

    private void start() {
        JFrame frame = new JFrame("KITA Job Recommender");
        frame.setDefaultCloseOperation(WindowConstants.EXIT_ON_CLOSE);
        frame.setSize(980, 720);
        frame.setLocationRelativeTo(null);

        JPanel root = new JPanel(new BorderLayout(16, 16));
        root.setBorder(new EmptyBorder(16, 16, 16, 16));

        root.add(buildHeader(), BorderLayout.NORTH);

        resultsPanel.setLayout(new BoxLayout(resultsPanel, BoxLayout.Y_AXIS));
        JScrollPane scrollPane = new JScrollPane(resultsPanel);
        scrollPane.setBorder(BorderFactory.createEmptyBorder());
        root.add(scrollPane, BorderLayout.CENTER);

        frame.setContentPane(root);
        frame.setVisible(true);

        recompute();
    }

    private JPanel buildHeader() {
        JPanel header = new JPanel();
        header.setLayout(new BoxLayout(header, BoxLayout.Y_AXIS));

        JLabel title = new JLabel("Top job picks for you");
        title.setFont(title.getFont().deriveFont(Font.BOLD, 20f));

        JPanel inputs = new JPanel(new FlowLayout(FlowLayout.LEFT, 10, 6));
        inputs.add(labeledField("Strand", strandField));
        inputs.add(labeledField("Location", locationField));
        inputs.add(labeledField("Skills (comma separated)", skillsField));

        JButton recommendBtn = new JButton("Recommend");
        recommendBtn.addActionListener(e -> recompute());
        JButton resetBtn = new JButton("Reset");
        resetBtn.addActionListener(e -> {
            strandField.setText("");
            locationField.setText("");
            skillsField.setText("");
            recompute();
        });

        inputs.add(recommendBtn);
        inputs.add(resetBtn);

        summaryLabel.setForeground(new Color(90, 90, 90));

        JLabel hint = new JLabel("Matching: strand, location, skills, job type, and recency.");
        hint.setForeground(new Color(110, 110, 110));

        header.add(title);
        header.add(Box.createVerticalStrut(6));
        header.add(inputs);
        header.add(Box.createVerticalStrut(4));
        header.add(summaryLabel);
        header.add(hint);
        return header;
    }

    private JPanel labeledField(String label, JTextField field) {
        JPanel panel = new JPanel(new BorderLayout(4, 2));
        JLabel lbl = new JLabel(label);
        lbl.setFont(lbl.getFont().deriveFont(12f));
        panel.add(lbl, BorderLayout.NORTH);
        panel.add(field, BorderLayout.CENTER);
        return panel;
    }

    private void recompute() {
        UserProfile profile = new UserProfile(
                strandField.getText(),
                locationField.getText(),
                skillsField.getText()
        );

        List<JobRecommendation> recs = new ArrayList<>();
        for (Job job : jobs) {
            recs.add(Scoring.score(job, profile));
        }

        recs.sort(new Comparator<JobRecommendation>() {
            @Override
            public int compare(JobRecommendation a, JobRecommendation b) {
                if (a.score != b.score) {
                    return b.score - a.score;
                }
                return b.job.id - a.job.id;
            }
        });

        resultsPanel.removeAll();
        if (recs.isEmpty()) {
            resultsPanel.add(emptyState());
        } else {
            for (JobRecommendation rec : recs) {
                resultsPanel.add(card(rec));
                resultsPanel.add(Box.createVerticalStrut(12));
            }
        }

        summaryLabel.setText(recs.size() + " matched jobs from your database");

        resultsPanel.revalidate();
        resultsPanel.repaint();
    }

    private JPanel emptyState() {
        JPanel panel = new JPanel(new BorderLayout());
        panel.setBorder(new EmptyBorder(16, 16, 16, 16));
        panel.add(new JLabel("No jobs found yet."), BorderLayout.CENTER);
        return panel;
    }

    private JPanel card(JobRecommendation rec) {
        JPanel card = new JPanel();
        card.setLayout(new BorderLayout(12, 10));
        card.setBorder(BorderFactory.createCompoundBorder(
                BorderFactory.createLineBorder(new Color(220, 220, 220)),
                new EmptyBorder(12, 12, 12, 12)
        ));

        JLabel title = new JLabel(rec.job.title);
        title.setFont(title.getFont().deriveFont(Font.BOLD, 16f));

        JLabel company = new JLabel(rec.job.company);
        company.setForeground(new Color(70, 70, 70));

        JLabel score = new JLabel(rec.score + "% match");
        score.setForeground(new Color(9, 140, 78));
        score.setFont(score.getFont().deriveFont(Font.BOLD, 14f));

        JPanel top = new JPanel(new BorderLayout());
        JPanel left = new JPanel();
        left.setLayout(new BoxLayout(left, BoxLayout.Y_AXIS));
        left.add(title);
        left.add(Box.createVerticalStrut(4));
        left.add(company);
        top.add(left, BorderLayout.CENTER);
        top.add(score, BorderLayout.EAST);

        JLabel meta = new JLabel(rec.job.location + " | " + rec.job.jobType + " | " + rec.job.salary);
        meta.setForeground(new Color(110, 110, 110));

        JTextArea desc = new JTextArea(rec.job.description);
        desc.setLineWrap(true);
        desc.setWrapStyleWord(true);
        desc.setEditable(false);
        desc.setOpaque(false);
        desc.setForeground(new Color(60, 60, 60));

        JPanel tags = new JPanel(new FlowLayout(FlowLayout.LEFT, 6, 0));
        for (String tag : rec.tags) {
            JLabel chip = new JLabel(tag);
            chip.setOpaque(true);
            chip.setBackground(new Color(236, 242, 247));
            chip.setBorder(new EmptyBorder(4, 8, 4, 8));
            tags.add(chip);
        }

        JPanel body = new JPanel();
        body.setLayout(new BoxLayout(body, BoxLayout.Y_AXIS));
        body.add(meta);
        body.add(Box.createVerticalStrut(6));
        body.add(desc);
        body.add(Box.createVerticalStrut(8));
        body.add(tags);

        card.add(top, BorderLayout.NORTH);
        card.add(body, BorderLayout.CENTER);
        return card;
    }

    private static List<Job> sampleJobs() {
        List<Job> data = new ArrayList<>();
        data.add(new Job(301, "UI/UX Design Intern", "BrightPixel Studio",
                "Create wireframes, mockups, and help with user testing for mobile apps.",
                "ICT", "Figma, User Research, Prototyping", "Quezon City", "PHP 12,000", "internship",
                LocalDate.now().minusDays(2)));
        data.add(new Job(302, "Junior Front-End Developer", "Nimbus Labs",
                "Build responsive web components and ship UI improvements with the team.",
                "ICT", "HTML, CSS, JavaScript, React", "Makati", "PHP 28,000", "full-time",
                LocalDate.now().minusDays(7)));
        data.add(new Job(303, "Accounting Assistant", "LedgerWorks",
                "Support payroll processing and assist with monthly reporting.",
                "ABM", "Excel, Bookkeeping", "Pasig", "PHP 18,000", "full-time",
                LocalDate.now().minusDays(12)));
        data.add(new Job(304, "Marketing Coordinator", "Harbor & Co.",
                "Coordinate campaigns, manage social media calendars, and track analytics.",
                "HUMSS", "Content Writing, Analytics, Social Media", "Quezon City", "PHP 20,000", "part-time",
                LocalDate.now().minusDays(1)));
        data.add(new Job(305, "Electrical Technician", "Voltline", "Assist with wiring, inspection, and safety checks.",
                "STEM", "Circuit Analysis, Safety", "Taguig", "PHP 22,000", "full-time",
                LocalDate.now().minusDays(4)));
        return data;
    }

    private static final class UserProfile {
        private final String strand;
        private final String location;
        private final List<String> skills;

        UserProfile(String strand, String location, String rawSkills) {
            this.strand = strand == null ? "" : strand;
            this.location = location == null ? "" : location;
            this.skills = splitSkills(rawSkills);
        }

        private static List<String> splitSkills(String raw) {
            List<String> skills = new ArrayList<>();
            if (raw == null) return skills;
            for (String part : raw.split(",")) {
                String s = part.trim();
                if (!s.isEmpty()) skills.add(s);
            }
            return skills;
        }
    }

    private static final class JobRecommendation {
        private final Job job;
        private final int score;
        private final List<String> tags;

        JobRecommendation(Job job, int score, List<String> tags) {
            this.job = job;
            this.score = score;
            this.tags = tags;
        }
    }

    private static final class Job {
        private final int id;
        private final String title;
        private final String company;
        private final String description;
        private final String strandRequired;
        private final String skillsRequired;
        private final String location;
        private final String salary;
        private final String jobType;
        private final LocalDate createdAt;

        Job(int id, String title, String company, String description, String strandRequired,
            String skillsRequired, String location, String salary, String jobType, LocalDate createdAt) {
            this.id = id;
            this.title = title;
            this.company = company;
            this.description = description;
            this.strandRequired = strandRequired;
            this.skillsRequired = skillsRequired;
            this.location = location;
            this.salary = salary;
            this.jobType = jobType;
            this.createdAt = createdAt;
        }
    }

    private static final class Scoring {
        private static JobRecommendation score(Job job, UserProfile profile) {
            int score = 35;
            List<String> matchedSkills = new ArrayList<>();

            String userStrand = normalize(profile.strand);
            String userLocation = normalize(profile.location);
            List<String> userSkillsNorm = new ArrayList<>();
            for (String s : profile.skills) {
                userSkillsNorm.add(normalize(s));
            }

            String jobStrand = normalize(job.strandRequired);
            if (!userStrand.isEmpty() && !jobStrand.isEmpty()) {
                if (userStrand.equals(jobStrand)) {
                    score += 30;
                } else if (jobStrand.contains(userStrand) || userStrand.contains(jobStrand)) {
                    score += 18;
                }
            } else if (jobStrand.isEmpty()) {
                score += 6;
            }

            String jobLocation = normalize(job.location);
            if (!userLocation.isEmpty() && !jobLocation.isEmpty()) {
                if (userLocation.equals(jobLocation)) {
                    score += 20;
                } else if (jobLocation.contains(userLocation) || userLocation.contains(jobLocation)) {
                    score += 12;
                }
            }

            String haystack = normalize(job.title + " " + job.description + " " + job.skillsRequired);
            String jobSkills = normalize(job.skillsRequired);
            for (int i = 0; i < userSkillsNorm.size(); i++) {
                String skillNorm = userSkillsNorm.get(i);
                if (skillNorm.isEmpty()) continue;
                if (haystack.contains(skillNorm)) {
                    score += 8;
                    if (!jobSkills.isEmpty() && jobSkills.contains(skillNorm)) {
                        score += 4;
                    }
                    matchedSkills.add(profile.skills.get(i));
                    if (matchedSkills.size() >= 4) break;
                }
            }

            String jobType = normalize(job.jobType);
            if (jobType.equals("internship") || jobType.equals("part-time")) {
                score += 6;
            }

            if (job.createdAt != null) {
                long ageDays = ChronoUnit.DAYS.between(job.createdAt, LocalDate.now());
                if (ageDays <= 3) {
                    score += 8;
                } else if (ageDays <= 10) {
                    score += 4;
                }
            }

            score = Math.max(0, Math.min(99, score));

            List<String> tags = buildTags(job, matchedSkills);
            return new JobRecommendation(job, score, tags);
        }

        private static List<String> buildTags(Job job, List<String> matchedSkills) {
            List<String> tags = new ArrayList<>();
            if (!job.jobType.isEmpty()) {
                tags.add(capitalize(job.jobType));
            }
            if (!job.strandRequired.isEmpty()) {
                tags.add(job.strandRequired);
            }
            if (!job.skillsRequired.isEmpty()) {
                for (String raw : job.skillsRequired.split(",")) {
                    String clean = raw.trim();
                    if (!clean.isEmpty()) tags.add(clean);
                    if (tags.size() >= 7) break;
                }
            }
            tags.addAll(matchedSkills);
            if (tags.isEmpty()) tags.add("General");
            return unique(tags);
        }

        private static List<String> unique(List<String> tags) {
            List<String> out = new ArrayList<>();
            Set<String> seen = new HashSet<>();
            for (String t : tags) {
                String key = normalize(t);
                if (seen.add(key)) out.add(t);
            }
            return out;
        }

        private static String normalize(String value) {
            if (value == null) return "";
            String v = value.trim().toLowerCase(Locale.ROOT);
            return v.replaceAll("\\s+", " ");
        }

        private static String capitalize(String value) {
            if (value == null || value.isEmpty()) return "";
            return value.substring(0, 1).toUpperCase(Locale.ROOT) + value.substring(1);
        }
    }
}
