<?php
require_once __DIR__ . '/config.php';

if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$status = $_GET['status'] ?? '';
$message = $_GET['message'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Sign Up - KITA</title>
    <link rel="stylesheet" href="style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        .register-theme #form {
            width: 100vw;
            height: 100vh;
            max-width: none;
            border-radius: 0;
            box-shadow: none;
            outline: 0;
            background: linear-gradient(180deg, rgba(22, 22, 22, 0.96), rgba(8, 28, 18, 0.92));
        }

        .register-theme #form-body {
            width: min(560px, 92vw);
            left: 50%;
            right: auto;
            transform: translateX(-50%);
            margin-top: -280px;
        }

        .register-theme #welcome-line-1 {
            font-size: 56px;
        }

        .register-theme #welcome-line-2 {
            margin-top: 14px;
            font-size: 24px;
            color: #c6c6c6;
        }

        .register-theme #input-area {
            margin-top: 26px;
        }

        .register-theme .step-progress {
            margin-top: 16px;
            margin-bottom: 16px;
            color: #9fd7bb;
            font-size: 13px;
            text-align: center;
        }

        .register-theme .step-panel {
            display: none;
        }

        .register-theme .step-panel.active {
            display: block;
        }

        .register-theme .step-panel.drop-in {
            animation: registerStepDrop 420ms cubic-bezier(0.2, 0.82, 0.23, 1);
        }

        .register-theme .text-drop-in {
            animation: registerTextDrop 480ms cubic-bezier(0.2, 0.82, 0.23, 1) both;
            animation-delay: var(--text-delay, 0ms);
        }

        @keyframes registerStepDrop {
            0% {
                opacity: 0;
                transform: translateY(-20px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes registerTextDrop {
            0% {
                opacity: 0;
                transform: translateY(-14px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .register-theme .step-label {
            margin: 0 0 10px;
            color: #b7c5bc;
            font-size: 14px;
            text-align: left;
        }

        .register-theme .form-inp {
            margin-bottom: 10px;
        }

        .register-theme .form-inp:last-child {
            margin-bottom: 0;
        }

        .register-theme .form-inp input {
            color: #d4ffe8;
            line-height: 1.35;
            border-radius: 6px;
            -webkit-appearance: none;
            appearance: none;
        }

        .register-theme .form-inp input:-webkit-autofill,
        .register-theme .form-inp input:-webkit-autofill:hover,
        .register-theme .form-inp input:-webkit-autofill:focus,
        .register-theme .form-inp input:-webkit-autofill:active {
            -webkit-text-fill-color: #d4ffe8;
            caret-color: #d4ffe8;
            -webkit-box-shadow: 0 0 0 1000px rgba(8, 14, 12, 0.92) inset;
            box-shadow: 0 0 0 1000px rgba(8, 14, 12, 0.92) inset;
            border-radius: 6px;
            transition: background-color 9999s ease-in-out 0s;
        }

        .register-theme .register-input-wrap {
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            gap: 8px;
        }

        .register-theme .register-eye {
            border: 0;
            background: transparent;
            color: #9ad9b9;
            cursor: pointer;
            font-size: 15px;
            padding: 0 2px;
        }

        .register-theme .register-help {
            margin: 6px 0 0;
            color: #9a9a9a;
            font-size: 12px;
            text-align: left;
        }

        .register-theme .location-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 8px;
        }

        .register-theme .register-select {
            width: 100%;
            border: 1px solid #4f665a;
            background: rgba(8, 28, 18, 0.92);
            color: #d4ffe8;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 14px;
            outline: none;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%2300ff7f' stroke-width='1.8' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 32px;
            cursor: pointer;
            transition: border-color 0.18s, box-shadow 0.18s;
        }

        .register-theme .register-select:focus {
            border-color: #00ff7f;
            box-shadow: 0 0 0 3px rgba(0, 255, 127, 0.15);
        }

        .register-theme .register-select option {
            background: #0d1f16;
            color: #d4ffe8;
        }

        .register-theme .register-select:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        .register-theme .quick-skill-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 8px;
        }

        .register-theme .skill-chip {
            border: 1px solid #4f665a;
            background: rgba(0, 0, 0, 0.24);
            color: #c8d9cf;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 12px;
            cursor: pointer;
        }

        .register-theme .skill-chip.active {
            border-color: #00ff7f;
            background: rgba(0, 255, 127, 0.14);
            color: #00ff7f;
        }

        .register-theme .custom-skill-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 8px;
            margin-bottom: 8px;
        }

        .register-theme .add-skill-btn {
            border: 1px solid #00ff7f;
            background: transparent;
            color: #00ff7f;
            border-radius: 8px;
            padding: 0 12px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
        }

        .register-theme .selected-skills {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            min-height: 28px;
        }

        .register-theme .selected-skill-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #00ff7f;
            background: rgba(0, 255, 127, 0.12);
            color: #00ff7f;
            border-radius: 999px;
            padding: 4px 9px;
            font-size: 12px;
        }

        .register-theme .selected-skill-remove {
            border: 0;
            background: transparent;
            color: #00ff7f;
            cursor: pointer;
            font-size: 12px;
            line-height: 1;
        }

        .register-theme .step-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 16px;
        }

        .register-theme .step-actions.single {
            grid-template-columns: 1fr;
        }

        .register-theme .step-btn {
            display: block;
            width: 100%;
            color: #00ff7f;
            background-color: transparent;
            font-weight: 600;
            font-size: 16px;
            margin: 0;
            padding: 14px 12px;
            border: 0;
            outline: 1px solid #00ff7f;
            border-radius: 8px;
            line-height: 1;
            cursor: pointer;
            transition: all ease-in-out .3s;
        }

        .register-theme .step-btn:hover {
            background-color: #00ff7f;
            color: #161616;
        }

        .register-theme #submit-button-cvr {
            margin-top: 0;
        }

        .register-theme #register-link {
            margin-top: 10px;
        }

        .register-theme .login-error {
            margin-top: 0;
            margin-bottom: 14px;
        }

        @media (max-width: 560px) {
            .register-theme #form {
                width: 100vw;
                height: 100vh;
                padding: 18px;
            }

            .register-theme #form-body {
                width: min(94vw, 560px);
                left: 50%;
                right: auto;
                transform: translateX(-50%);
                margin-top: -285px;
            }

            .register-theme #welcome-line-1 {
                font-size: 44px;
            }
        }
    </style>
</head>
<body class="login-page register-theme">
    <div class="login-bg-fly" aria-hidden="true">
        <img class="fly-logo fly-1" src="uploads/kita_logo.png" alt="" />
        <img class="fly-logo fly-2" src="uploads/kita_logo.png" alt="" />
        <img class="fly-logo fly-3" src="uploads/kita_logo.png" alt="" />
        <img class="fly-logo fly-4" src="uploads/kita_logo.png" alt="" />
        <img class="fly-logo fly-5" src="uploads/kita_logo.png" alt="" />
        <img class="fly-logo fly-6" src="uploads/kita_logo.png" alt="" />
    </div>

    <div id="form-ui">
        <form action="register_process.php" method="post" id="form">
            <div id="form-body">
                <div id="welcome-lines">
                    <div id="welcome-line-1">JOIN KITA</div>
                    <div id="welcome-line-2">Create your account and start your journey.</div>
                </div>

                <?php if ($message !== ''): ?>
                    <div class="login-error"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <div class="step-progress" id="stepProgress">Step 1 of 6</div>

                <div id="input-area">
                    <div class="step-panel active" data-step="1">
                        <p class="step-label">Choose a username</p>
                        <div class="form-inp">
                            <input type="text" name="username" placeholder="Username" minlength="3" maxlength="30" required />
                        </div>
                    </div>
                    <div class="step-panel" data-step="2">
                        <p class="step-label">Enter your email</p>
                        <div class="form-inp">
                            <input type="email" name="email" placeholder="Email" required />
                        </div>
                    </div>
                    <div class="step-panel" data-step="3">
                        <p class="step-label">Create a password</p>
                        <div class="form-inp">
                            <div class="register-input-wrap">
                                <input id="registerPassword" type="password" name="password" placeholder="Password" minlength="6" required />
                                <button class="register-eye" id="togglePasswordBtn" type="button" aria-label="Show password">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="step-panel" data-step="4">
                        <p class="step-label">Where are you located?</p>
                        <div class="location-grid">
                            <select class="register-select" id="regionSelect">
                                <option value="">Select region</option>
                                <option value="NCR">NCR</option>
                                <option value="CAR">CAR</option>
                                <option value="Region I">Region I</option>
                                <option value="Region III">Region III</option>
                                <option value="Region IV-A">Region IV-A</option>
                                <option value="Region V">Region V</option>
                                <option value="Region VI">Region VI</option>
                                <option value="Region VII">Region VII</option>
                                <option value="Region VIII">Region VIII</option>
                                <option value="Region IX">Region IX</option>
                                <option value="Region X">Region X</option>
                                <option value="Region XI">Region XI</option>
                                <option value="Region XII">Region XII</option>
                            </select>
                            <select class="register-select" id="citySelect" disabled>
                                <option value="">Select city</option>
                            </select>
                        </div>
                        <div class="form-inp">
                            <input type="text" id="locationInput" name="location" placeholder="City, Province" maxlength="120" required />
                        </div>
                        <p class="register-help">Quick pick using region/city, or type your location manually.</p>
                    </div>
                    <div class="step-panel" data-step="5">
                        <p class="step-label">Choose your skills</p>
                        <div class="form-inp">
                            <input type="text" name="skills" id="skillsInput" placeholder="Pick from suggestions below" maxlength="255" readonly required />
                        </div>
                        <div class="quick-skill-grid" id="quickSkillGrid">
                            <button class="skill-chip" type="button" data-skill="HTML">HTML</button>
                            <button class="skill-chip" type="button" data-skill="CSS">CSS</button>
                            <button class="skill-chip" type="button" data-skill="JavaScript">JavaScript</button>
                            <button class="skill-chip" type="button" data-skill="PHP">PHP</button>
                            <button class="skill-chip" type="button" data-skill="Python">Python</button>
                            <button class="skill-chip" type="button" data-skill="Java">Java</button>
                            <button class="skill-chip" type="button" data-skill="SQL">SQL</button>
                            <button class="skill-chip" type="button" data-skill="React">React</button>
                            <button class="skill-chip" type="button" data-skill="Node.js">Node.js</button>
                            <button class="skill-chip" type="button" data-skill="Figma">Figma</button>
                            <button class="skill-chip" type="button" data-skill="UI Design">UI Design</button>
                            <button class="skill-chip" type="button" data-skill="UX Design">UX Design</button>
                            <button class="skill-chip" type="button" data-skill="Git">Git</button>
                            <button class="skill-chip" type="button" data-skill="Canva">Canva</button>
                            <button class="skill-chip" type="button" data-skill="Data Analysis">Data Analysis</button>
                            <button class="skill-chip" type="button" data-skill="Communication">Communication</button>
                        </div>
                        <div class="custom-skill-row">
                            <input type="text" id="customSkillInput" placeholder="Add custom skill" maxlength="50" />
                            <button class="add-skill-btn" id="addCustomSkillBtn" type="button">Add</button>
                        </div>
                        <div class="selected-skills" id="selectedSkillsWrap"></div>
                        <p class="register-help">Tap skills to select. You can also add your own.</p>
                    </div>
                    <div class="step-panel" data-step="6">
                        <p class="step-label">Choose your SHS strand</p>
                        <div class="form-inp">
                            <input type="text" name="strand" list="strand-options" placeholder="SHS Strand (type to search)" required />
                            <datalist id="strand-options">
                                <option value="STEM"></option>
                                <option value="ABM"></option>
                                <option value="HUMSS"></option>
                                <option value="TVL"></option>
                                <option value="Arts and Design"></option>
                                <option value="Sports"></option>
                            </datalist>
                        </div>
                    </div>
                </div>

                <div class="step-actions single" id="stepActions">
                    <button class="step-btn" id="nextStepBtn" type="button">Next</button>
                </div>
                <div id="register-link">
                    <a href="login.php">Already have an account? Log in here</a>
                </div>
                <div id="register-link">
                    <a href="employer_register.php">Employer? Create employer account</a>
                </div>
                <div id="bar"></div>
            </div>
        </form>
    </div>

    <script>
        const passwordInput = document.getElementById("registerPassword");
        const togglePasswordBtn = document.getElementById("togglePasswordBtn");
        const form = document.getElementById("form");
        const stepPanels = Array.from(document.querySelectorAll(".step-panel"));
        const stepProgress = document.getElementById("stepProgress");
        const stepActions = document.getElementById("stepActions");
        const regionSelect = document.getElementById("regionSelect");
        const citySelect = document.getElementById("citySelect");
        const locationInput = document.getElementById("locationInput");
        const skillsInput = document.getElementById("skillsInput");
        const skillChips = Array.from(document.querySelectorAll(".skill-chip"));
        const customSkillInput = document.getElementById("customSkillInput");
        const addCustomSkillBtn = document.getElementById("addCustomSkillBtn");
        const selectedSkillsWrap = document.getElementById("selectedSkillsWrap");
        const totalSteps = stepPanels.length;
        let currentStep = 1;
        let lastDirection = "init";

        if (passwordInput && togglePasswordBtn) {
            togglePasswordBtn.addEventListener("click", () => {
                const isPassword = passwordInput.type === "password";
                passwordInput.type = isPassword ? "text" : "password";
                togglePasswordBtn.innerHTML = isPassword
                    ? '<i class="fa-regular fa-eye-slash"></i>'
                    : '<i class="fa-regular fa-eye"></i>';
            });
        }

        const locationMap = {
            "NCR": ["Quezon City, Metro Manila", "Manila, Metro Manila", "Makati, Metro Manila", "Taguig, Metro Manila", "Pasig, Metro Manila"],
            "CAR": ["Baguio City, Benguet", "La Trinidad, Benguet", "Tabuk City, Kalinga", "Bangued, Abra"],
            "Region I": ["Dagupan City, Pangasinan", "Urdaneta City, Pangasinan", "Laoag City, Ilocos Norte", "Vigan City, Ilocos Sur"],
            "Region III": ["Angeles City, Pampanga", "San Fernando City, Pampanga", "Cabanatuan City, Nueva Ecija", "Olongapo City, Zambales"],
            "Region IV-A": ["Antipolo City, Rizal", "Calamba City, Laguna", "Santa Rosa City, Laguna", "Batangas City, Batangas", "Bacoor City, Cavite"],
            "Region V": ["Naga City, Camarines Sur", "Legazpi City, Albay", "Sorsogon City, Sorsogon"],
            "Region VI": ["Iloilo City, Iloilo", "Bacolod City, Negros Occidental", "Roxas City, Capiz"],
            "Region VII": ["Cebu City, Cebu", "Mandaue City, Cebu", "Lapu-Lapu City, Cebu", "Tagbilaran City, Bohol"],
            "Region VIII": ["Tacloban City, Leyte", "Ormoc City, Leyte", "Catbalogan City, Samar"],
            "Region IX": ["Zamboanga City, Zamboanga del Sur", "Pagadian City, Zamboanga del Sur", "Dipolog City, Zamboanga del Norte"],
            "Region X": ["Cagayan de Oro City, Misamis Oriental", "Iligan City, Lanao del Norte", "Valencia City, Bukidnon"],
            "Region XI": ["Davao City, Davao del Sur", "Tagum City, Davao del Norte", "Panabo City, Davao del Norte"],
            "Region XII": ["General Santos City, South Cotabato", "Koronadal City, South Cotabato", "Kidapawan City, Cotabato"]
        };

        const selectedSkills = new Set();

        function populateCities(region) {
            if (!citySelect) return;
            const cities = locationMap[region] || [];
            citySelect.innerHTML = '<option value="">Select city</option>' +
                cities.map((city) => `<option value="${city}">${city}</option>`).join("");
            citySelect.disabled = cities.length === 0;
        }

        function normalizeSkill(value) {
            return value.trim().replace(/\s+/g, " ");
        }

        function syncSkillsInput() {
            if (!skillsInput) return;
            skillsInput.value = Array.from(selectedSkills).join(", ");
            skillChips.forEach((chip) => {
                const skill = normalizeSkill(chip.dataset.skill || "");
                chip.classList.toggle("active", selectedSkills.has(skill));
            });
        }

        function renderSelectedSkills() {
            if (!selectedSkillsWrap) return;
            if (!selectedSkills.size) {
                selectedSkillsWrap.innerHTML = "";
                return;
            }
            selectedSkillsWrap.innerHTML = Array.from(selectedSkills).map((skill) => `
                <span class="selected-skill-tag">
                    ${skill}
                    <button type="button" class="selected-skill-remove" data-remove-skill="${skill}" aria-label="Remove ${skill}">x</button>
                </span>
            `).join("");
        }

        function addSkill(rawSkill) {
            const skill = normalizeSkill(rawSkill);
            if (!skill) return;
            selectedSkills.add(skill);
            syncSkillsInput();
            renderSelectedSkills();
        }

        function removeSkill(rawSkill) {
            const skill = normalizeSkill(rawSkill);
            selectedSkills.delete(skill);
            syncSkillsInput();
            renderSelectedSkills();
        }

        regionSelect?.addEventListener("change", () => {
            populateCities(regionSelect.value);
            if (locationInput) {
                locationInput.value = "";
            }
        });

        citySelect?.addEventListener("change", () => {
            if (!locationInput) return;
            if (citySelect.value) locationInput.value = citySelect.value;
        });

        skillChips.forEach((chip) => {
            chip.addEventListener("click", () => {
                const skill = normalizeSkill(chip.dataset.skill || "");
                if (!skill) return;
                if (selectedSkills.has(skill)) {
                    removeSkill(skill);
                } else {
                    addSkill(skill);
                }
            });
        });

        addCustomSkillBtn?.addEventListener("click", () => {
            const value = customSkillInput?.value || "";
            addSkill(value);
            if (customSkillInput) customSkillInput.value = "";
            customSkillInput?.focus();
        });

        customSkillInput?.addEventListener("keydown", (event) => {
            if (event.key === "Enter") {
                event.preventDefault();
                addCustomSkillBtn?.click();
            }
        });

        selectedSkillsWrap?.addEventListener("click", (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) return;
            const skill = target.getAttribute("data-remove-skill");
            if (!skill) return;
            removeSkill(skill);
        });

        function validateCurrentStep() {
            const activePanel = stepPanels[currentStep - 1];
            if (!activePanel) return false;
            const field = activePanel.querySelector("input");
            if (!field) return true;
            return field.reportValidity();
        }

        function updateStepUI() {
            stepPanels.forEach((panel, index) => {
                panel.classList.toggle("active", index === currentStep - 1);
            });

            stepProgress.textContent = `Step ${currentStep} of ${totalSteps}`;

            if (currentStep === 1) {
                stepActions.className = "step-actions single";
                stepActions.innerHTML = '<button class="step-btn" id="nextStepBtn" type="button">Next</button>';
            } else if (currentStep === totalSteps) {
                stepActions.className = "step-actions";
                stepActions.innerHTML = `
                    <button class="step-btn" id="prevStepBtn" type="button">Back</button>
                    <button id="submit-button" type="submit">Sign Up</button>
                `;
            } else {
                stepActions.className = "step-actions";
                stepActions.innerHTML = `
                    <button class="step-btn" id="prevStepBtn" type="button">Back</button>
                    <button class="step-btn" id="nextStepBtn" type="button">Next</button>
                `;
            }

            const nextBtn = document.getElementById("nextStepBtn");
            const prevBtn = document.getElementById("prevStepBtn");

            if (nextBtn) {
                nextBtn.addEventListener("click", () => {
                    if (!validateCurrentStep()) return;
                    lastDirection = "next";
                    currentStep += 1;
                    updateStepUI();
                });
            }

            if (prevBtn) {
                prevBtn.addEventListener("click", () => {
                    lastDirection = "prev";
                    currentStep -= 1;
                    updateStepUI();
                });
            }

            if (lastDirection === "next") {
                const activePanel = stepPanels[currentStep - 1];
                if (activePanel) {
                    activePanel.classList.remove("drop-in");
                    void activePanel.offsetWidth;
                    activePanel.classList.add("drop-in");
                }

                const textTargets = [
                    document.getElementById("welcome-line-1"),
                    document.getElementById("welcome-line-2"),
                    stepProgress,
                    ...activePanel.querySelectorAll("p, input, button, span"),
                    ...stepActions.querySelectorAll("button"),
                    ...document.querySelectorAll("#register-link a")
                ].filter(Boolean);

                textTargets.forEach((el, idx) => {
                    el.classList.remove("text-drop-in");
                    el.style.setProperty("--text-delay", `${Math.min(idx * 35, 280)}ms`);
                    void el.offsetWidth;
                    el.classList.add("text-drop-in");
                });
            }
        }

        form.addEventListener("submit", (event) => {
            if (!validateCurrentStep()) {
                event.preventDefault();
            }
        });

        updateStepUI();
    </script>
</body>
</html>

