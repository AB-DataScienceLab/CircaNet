<?php include 'header.php'; ?>

<!-- Page-Specific CSS -->
<style>
    /* Styling for the Team Member Cards */
    .member-card {
        background-color: #fff;
        border: none;
        border-radius: 12px;
        padding: 30px 20px;
        text-align: center;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        height: 100%; 
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .member-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(16, 66, 141, 0.15);
    }

    /* Circular Profile Images */
    .member-img {
        width: 150px;
        height: 150px;
        object-fit: cover;
        border-radius: 50%;
        border: 4px solid #f0f0f0;
        margin-bottom: 20px;
    }

    /* Typography */
    .member-name {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 5px;
    }
    
    .member-name a {
        color: #10428d;
        text-decoration: none;
        transition: color 0.2s;
    }
    
    .member-name a:hover {
        color: #d63384;
    }

    .member-role {
        color: #d63384; 
        font-weight: 600;
        font-size: 1rem;
        margin-bottom: 8px;
    }

    .member-org {
        color: #6c757d;
        font-size: 0.9rem;
        line-height: 1.4;
    }

    /* Section Heading Style */
    .page-heading {
        color: #10428d;
        font-weight: 300;
        text-align: center;
        margin-bottom: 40px;
        position: relative;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .page-heading::after {
        content: '';
        display: block;
        width: 60px;
        height: 3px;
        background-color: #10428d;
        margin: 15px auto 0;
    }

    /* Map Styling */
    .map-wrapper {
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        background: #fff;
        padding: 10px;
    }

    /* Constrain the 2x2 grid so it doesn't get too wide on desktop */
    .team-container {
        max-width: 900px;
        margin: 0 auto;
    }
</style>

<!-- CONTENT START -->
    <div class="row">
        <div class="col-12">
            <h1 class="page-heading">Our Team</h1>
        </div>
    </div>

    <!-- Team Grid System - Wrapped in team-container for 2x2 layout -->
    <div class="team-container">
        <div class="row justify-content-center">
        
            <!-- 1. Ms. Shweta Pandey -->
            <div class="col-md-6 mb-4">
                <div class="member-card">
                    <img src="photos/spd.jpg" alt="Ms. Shweta Pandey" class="member-img">
                    <h3 class="member-name">
                        <a href="#">Ms. Shweta Pandey</a>
                    </h3>
                    <div class="member-role">Senior Research Fellow</div>
                    <div class="member-org">Bioinformatics Centre</div>
                    <div class="member-org">CSIR-IMTech, Chandigarh</div>
                </div>
            </div>

            <!-- 2. Mr. Raghav Sankhdher -->
            <div class="col-md-6 mb-4">
                <div class="member-card">
                    <img src="photos/raghav.jpg" alt="Mr. Raghav Sankhdher" class="member-img">
                    <h3 class="member-name">
                        <a href="#">Mr. Raghav Sankhdher</a>
                    </h3>
                    <div class="member-role">Senior Project Associate</div>
                    <div class="member-org">Bioinformatics Centre</div>
                    <div class="member-org">CSIR-IMTech, Chandigarh</div>
                </div>
            </div>

            <!-- 3. Mr. Harsh Bajetha -->
            <div class="col-md-6 mb-4">
                <div class="member-card">
                    <img src="photos/harsh.jpeg" alt="Mr. Harsh Bajetha" class="member-img">
                    <h3 class="member-name">
                        <a href="#">Mr. Harsh Bajetha</a>
                    </h3>
                    <div class="member-role">Junior Research Fellow</div>
                    <div class="member-org">Bioinformatics Centre</div>
                    <div class="member-org">CSIR-IMTech, Chandigarh</div>
                </div>
            </div>

                   <!-- 4. Mr. Rounak Kumawat -->
            <div class="col-md-6 mb-4">
                <div class="member-card">
                    <img src="photos/Rounak_copy.jpeg" alt="Mr. Rounak Kumawat" class="member-img">
                    <h3 class="member-name">
                        <a href="#">Mr. Rounak Kumawat</a>
                    </h3>
                    <div class="member-role">Project Associate - I</div>
                    <div class="member-org">Bioinformatics Centre</div>
                    <div class="member-org">CSIR-IMTech, Chandigarh</div>
                </div>
            </div>

            
                   <!-- 5. Dr. Prasun Kumar-->
            <div class="col-md-6 mb-4">
                <div class="member-card">
                    <img src="photos/Dr. Prasun Kumar.jpg" alt="Dr. Prasun Kumar" class="member-img">
                    <h3 class="member-name">
                        <a href="#">Dr. Prasun Kumar</a>
                    </h3>
                    <div class="member-role">Assistant Professor</div>
                    <div class="member-org">Biological Sciences and Engineering</div>
                    <div class="member-org">Indian Institute of Technology Palakkad</div>
                </div>
            </div>

            <!-- 6. Dr. Anshu Bhardwaj -->
            <div class="col-md-6 mb-4">
                <div class="member-card">
                    <img src="photos/anshu.jpg" alt="Dr. Anshu Bhardwaj" class="member-img">
                    <h3 class="member-name">
                        <a href="mailto:anshub@osdd.net">Dr. Anshu Bhardwaj</a>
                    </h3>
                    <div class="member-role">Scientist F</div>
                    <div class="member-org">Bioinformatics Centre</div>
                    <div class="member-org">CSIR-IMTech, Chandigarh</div>
                </div>
            </div>

        </div>
    </div>

<!--    <!-- Map Section -->
<!--    <div class="row mt-5 mb-5">-->
<!--        <div class="col-12 text-center">-->
<!--            <h2 class="page-heading" style="font-size: 1.5rem;">Reach CSIR-IMTech, Chandigarh</h2>-->
<!--            <div class="map-wrapper">-->
<!--                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3429.0075085464823!2d76.73144557527894!3d30.746290584977775!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x390fede2a3e1498f%3A0xe9e249a6b5b8e967!2sCSIR%20%E2%80%93%20Institute%20Of%20Microbial%20Technology%20(IMTECH)!5e0!3m2!1sen!2sin!4v1719998734071!5m2!1sen!2sin" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>-->
<!--            </div>-->
<!--        </div>-->
<!--    </div>-->

</main>

<?php include 'footer.php'; ?>