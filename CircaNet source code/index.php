<?php 
// --- AJAX ENDPOINT FOR AUTO-SUGGESTION ---
if (isset($_GET['ajax_suggest'])) {
    include 'conn.php';
    $term = trim($_GET['ajax_suggest']) . '%'; // Search from the beginning of the symbol
    $sql = "SELECT Symbol FROM Gene_tb_new_import WHERE Symbol LIKE ? LIMIT 10";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $term);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $data = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $data[] = $row['Symbol'];
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
// -----------------------------------------

// Load the header ONLY ONCE
include 'header.php'; 
?>

<!-- Custom Styles for Index Page -->
<style>
   body {
        background-color: #f4f7fa; 
    }

    .hero-section {
        padding: 0px 0;
        background: linear-gradient(180deg, #f4f7fa 0%, #ffffff 100%); 
    }

    .hero-title {
        color: #2A4B7C; 
        font-weight: 700;
        font-size: 3.2rem;
        letter-spacing: -0.5px;
        line-height: 1.2;
    }

    /* Fixed to 960px max-width and forced to a 16:9 aspect ratio */
    .slideshow-container {
        max-width: 960px; 
        width: 100%;
        aspect-ratio: 16 / 9; /* Keeps the container proportional (960x540) */
        position: relative;
        margin: auto;
        background: #ffffff; /* Fills outer empty spaces if images don't match 16:9 */
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        border-radius: 8px;
        overflow: hidden;
    }

    /* Fallback for browsers that do not support aspect-ratio */
    @supports not (aspect-ratio: 16 / 9) {
        .slideshow-container {
            height: 540px;
        }
    }

    .mySlides {
        display: none;
        width: 100%;
        height: 100%;
    }

    /* 
       object-fit: contain ensures the image fits inside the box 
       without stretching, cropping, or losing its original aspect ratio.
    */
    .mySlides img {
        width: 100% !important;
        height: 100% !important;
        object-fit: contain; 
        background-color: #ffffff; /* Optional: background color for letterbox/pillarbox areas */
        display: block;
    }

    .prev, .next {
        cursor: pointer;
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: auto;
        padding: 24px 16px;
        color: white;
        font-weight: bold;
        font-size: 22px;
        transition: 0.3s ease;
        user-select: none;
        background-color: rgba(42, 75, 124, 0.4); 
        text-decoration: none;
        z-index: 10;
    }

    .prev { left: 0; border-radius: 0 4px 4px 0; }
    .next { right: 0; border-radius: 4px 0 0 4px; }

    .prev:hover, .next:hover {
        background-color: rgba(42, 75, 124, 0.8);
        color: white;
    }

    .dot {
        cursor: pointer;
        height: 12px;
        width: 12px;
        margin: 0 5px;
        background-color: #cbd5e1; 
        border-radius: 50%;
        display: inline-block;
        transition: background-color 0.3s ease;
    }

    .active, .dot:hover {
        background-color: #E89D6C; 
    }

    .fade-slide {
        animation-name: fadeEffect;
        animation-duration: 1s;
    }

    @keyframes fadeEffect {
        from { opacity: .5 }
        to { opacity: 1 }
    }
    
    .input-group-text {
        background-color: white;
        border: 2px solid #e2e8f0;
        border-right: none;
        border-top-left-radius: 8px;
        border-bottom-left-radius: 8px;
    }

    .hero-search {
        border: 2px solid #e2e8f0;
        border-left: none; 
        padding: 15px 10px;
        font-size: 1.1rem;
        height: auto;
    }

    .hero-search:focus {
        border-color: #5B8CBE; 
        box-shadow: none;
    }
    
    .btn-hero {
        background-color: #5B8CBE;
        border-color: #5B8CBE;
        padding: 0 30px;
        font-weight: 600;
        font-size: 1.1rem;
        border-top-right-radius: 8px;
        border-bottom-right-radius: 8px;
        color: white;
        transition: background-color 0.2s ease-in-out;
    }
    .btn-hero:hover {
        background-color: #2A4B7C; 
        border-color: #2A4B7C;
        color: white;
    }

    .suggestion-box {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        z-index: 1050;
        max-height: 250px;
        overflow-y: auto;
        box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        display: none;
        margin-top: 5px;
    }
    .suggestion-item {
        padding: 12px 20px;
        cursor: pointer;
        color: #333;
        border-bottom: 1px solid #f8fafc;
        text-align: left;
        font-size: 1.05rem;
        transition: background 0.2s;
    }
    .suggestion-item:last-child {
        border-bottom: none;
    }
    .suggestion-item:hover {
        background-color: #FFF5EE; 
        color: #E89D6C;
    }

    #bg-canvas {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -1;
        opacity: 0.55;
        pointer-events: none;
    }

    .about-card {
        background: #ffffff;
        border-radius: 16px;
        border-top: 5px solid #5B8CBE; 
        box-shadow: 0 4px 25px rgba(0, 0, 0, 0.05);
    }
    .about-title {
        color: #2A4B7C;
        font-weight: 700;
        font-size: 1.75rem;
    }
    .about-text {
        color: #4A5568;
        font-size: 1.05rem;
        line-height: 1.8;
        text-align: justify;
    }
</style>

<canvas id="bg-canvas"></canvas>

<section class="hero-section text-center">
    <div class="container">
        
        <!-- Slideshow Container -->
        <div class="row justify-content-center mb-3">
            <div class="col-12">
                <div class="slideshow-container p-0">
                    <div class="mySlides fade-slide">
                        <img src="GA/GA1.png" class="img-fluid rounded-2" alt="Circadian Rhythm Concept Slide 1">
                    </div>
                    <div class="mySlides fade-slide">
                        <img src="GA/GA2.png" class="img-fluid rounded-2" alt="Circadian Rhythm Concept Slide 2">
                    </div>
                     <div class="mySlides fade-slide">
                        <img src="GA/GA3.png" class="img-fluid rounded-2" alt="Circadian Rhythm Concept Slide 3">
                    </div>
                    <div class="mySlides fade-slide">
                        <img src="GA/GA4.png" class="img-fluid rounded-2" alt="Circadian Rhythm Concept Slide 4">
                    </div>
                    <div class="mySlides fade-slide">
                        <img src="GA/GA5.png" class="img-fluid rounded-2" alt="Circadian Rhythm Concept Slide 5">
                    </div>
				    <div class="mySlides fade-slide">
                        <img src="GA/GA6.png" class="img-fluid rounded-2" alt="Circadian Rhythm Concept Slide 6">
                    </div>
                    <div class="mySlides fade-slide">
                        <img src="GA/GA7.png" class="img-fluid rounded-2" alt="Circadian Rhythm Concept Slide 7">
                    </div>
                    <div class="mySlides fade-slide">
                        <img src="GA/GA8.png" class="img-fluid rounded-2" alt="Circadian Rhythm Concept Slide 8">
                    </div>
                    <div class="mySlides fade-slide">
                        <img src="GA/GA9.png" class="img-fluid rounded-2" alt="Circadian Rhythm Concept Slide 9">
                    </div>
                    <!-- Navigation buttons -->
                    <a class="prev" onclick="plusSlides(-1)">&#10094;</a>
                    <a class="next" onclick="plusSlides(1)">&#10095;</a>
                </div>
                
                <!-- Dot Indicators -->
                <div class="text-center mt-3">
                    <span class="dot" onclick="currentSlide(1)"></span>
                    <span class="dot" onclick="currentSlide(2)"></span>
                    <span class="dot" onclick="currentSlide(3)"></span>
                    <span class="dot" onclick="currentSlide(4)"></span>
                    <span class="dot" onclick="currentSlide(5)"></span>
                    <span class="dot" onclick="currentSlide(6)"></span>
                    <span class="dot" onclick="currentSlide(7)"></span>
                    <span class="dot" onclick="currentSlide(8)"></span>
                    <span class="dot" onclick="currentSlide(9)"></span>
                </div>
            </div>
        </div>

        <!-- Search Section -->
        <div class="row justify-content-center">
            <div class="col-lg-12 col-xl-12">
                <form action="gene.php" method="GET" class="input-group mb-4 shadow-sm position-relative mx-auto" style="max-width: 650px;" autocomplete="off">
                    <span class="input-group-text">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input type="text" name="keyword" class="form-control hero-search border-start-0" placeholder="Search for Gene (e.g. PER2, CLOCK)..." required>
                    <button class="btn btn-hero" type="submit">Search</button>
                </form>
                
                <div class="d-flex justify-content-center gap-4 text-secondary small fw-bold mt-2 mb-4">
                    <span><i class="fas fa-server me-1" style="color: #5B8CBE;"></i> 16k+ Genes</span>
                    <span><i class="fas fa-project-diagram me-1" style="color: #E89D6C;"></i> ~280K Interactions</span>
                    <span><i class="fas fa-download me-1" style="color: #5B8CBE;"></i> Open Data</span>
                </div>
            </div>
        </div>

    </div>
</section>
<br>
<!-- About Section -->
<section class="container mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-70 col-xl-70">
            <div class="about-card p-4 p-md-5">
                <h3 class="about-title mb-4 text-center">About CircaNet</h3>
                <p class="about-text mb-3">
                    CircaNet is a comprehensive, curated, value-added knowledge base for circadian biology, integrating information from the molecular to population scale for a holistic understanding of human circadian biology. CircaNet systematically integrates circadian-associated genes, rhythmic expression, genetic variants, disease associations, molecular networks, tissue-specific regulation, inter-tissue communication, and orthologs. The curated data can be systematically searched, browsed, and retrieved following FAIR data principles, supporting candidate prioritisation and hypothesis generation in circadian research. 
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Auto-Suggest Script -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.querySelector('input[name="keyword"]');
    if (!searchInput) return;

    const form = searchInput.closest('form');
    const suggestionBox = document.createElement('div');
    suggestionBox.className = 'suggestion-box';
    form.appendChild(suggestionBox); 
    
    let debounceTimer;

    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const query = this.value.trim();
        
        if (query.length < 1) {
            suggestionBox.style.display = 'none';
            return;
        }
        
        debounceTimer = setTimeout(() => {
            fetch('index.php?ajax_suggest=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(data => {
                    if (data.length > 0) {
                        suggestionBox.innerHTML = '';
                        data.forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'suggestion-item';
                            const regex = new RegExp(`^(${query})`, 'gi');
                            div.innerHTML = item.replace(regex, "<strong>$1</strong>");
                            
                            div.addEventListener('click', function() {
                                searchInput.value = item;
                                suggestionBox.style.display = 'none';
                                form.submit(); 
                            });
                            suggestionBox.appendChild(div);
                        });
                        suggestionBox.style.display = 'block';
                    } else {
                        suggestionBox.style.display = 'none';
                    }
                })
                .catch(error => console.error('Error fetching suggestions:', error));
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (!form.contains(e.target)) {
            suggestionBox.style.display = 'none';
        }
    });
});
</script>

<!-- Slideshow Logic -->
<script>
    let slideIndex = 1;
    showSlides(slideIndex);

    function plusSlides(n) {
        showSlides(slideIndex += n);
    }

    function currentSlide(n) {
        showSlides(slideIndex = n);
    }

    function showSlides(n) {
        let i;
        let slides = document.getElementsByClassName("mySlides");
        let dots = document.getElementsByClassName("dot");
        if (n > slides.length) {
            slideIndex = 1
        }
        if (n < 1) {
            slideIndex = slides.length
        }
        for (i = 0; i < slides.length; i++) {
            slides[i].style.display = "none";
        }
        for (i = 0; i < dots.length; i++) {
            dots[i].className = dots[i].className.replace(" active", "");
        }
        if (slides[slideIndex - 1]) {
            slides[slideIndex - 1].style.display = "block";
        }
        if (dots[slideIndex - 1]) {
            dots[slideIndex - 1].className += " active";
        }
    }

    function autoSlideshow() {
        plusSlides(1);
        setTimeout(autoSlideshow, 10000); 
    }

    setTimeout(autoSlideshow, 10000);
</script>

<!-- Background Particles -->
<script>
    (function() {
        const canvas = document.getElementById('bg-canvas');
        const ctx = canvas.getContext('2d');
        let width, height;
        let particles = [];

        function resize() {
            width = window.innerWidth;
            height = window.innerHeight;
            canvas.width = width;
            canvas.height = height;
        }
        window.addEventListener('resize', resize);
        resize();

        class Particle {
            constructor() {
                this.x = Math.random() * width;
                this.y = Math.random() * height;
                this.vx = (Math.random() - 0.5) * 0.5;
                this.vy = (Math.random() - 0.5) * 0.5;
                this.size = Math.random() * 3 + 1;
                this.color = 'rgba(91, 140, 190, 0.15)'; 
            }
            update() {
                this.x += this.vx;
                this.y += this.vy;
                if (this.x < 0 || this.x > width) this.vx *= -1;
                if (this.y < 0 || this.y > height) this.vy *= -1;
            }
            draw() {
                ctx.fillStyle = this.color;
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                ctx.fill();
            }
        }

        for (let i = 0; i < 50; i++) {
            particles.push(new Particle());
        }

        function animate() {
            ctx.clearRect(0, 0, width, height);
            
            for (let i = 0; i < particles.length; i++) {
                particles[i].update();
                particles[i].draw();
                
                for (let j = i; j < particles.length; j++) {
                    const dx = particles[i].x - particles[j].x;
                    const dy = particles[i].y - particles[j].y;
                    const distance = Math.sqrt(dx * dx + dy * dy);
                    
                    if (distance < 120) {
                        ctx.beginPath();
                        ctx.strokeStyle = `rgba(91, 140, 190, ${0.15 - distance/800})`;
                        ctx.lineWidth = 1;
                        ctx.moveTo(particles[i].x, particles[i].y);
                        ctx.lineTo(particles[j].x, particles[j].y);
                        ctx.stroke();
                    }
                }
            }
            requestAnimationFrame(animate);
        }
        animate();
    })();
</script>

<?php include 'footer.php'; ?>