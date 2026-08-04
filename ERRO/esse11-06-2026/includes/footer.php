<?php
// includes/footer.php
?>
            </div>
        </main>
    </div>
    
<script src="/assets/js/app.js"></script>
<script src="/assets/js/theme.js"></script>
    <script>
        function updateDateTime() {
            const now = new Date();
            const dateElement = document.getElementById('currentDate');
            const timeElement = document.getElementById('currentTime');
            
            if (dateElement) {
                const options = { day: '2-digit', month: '2-digit', year: 'numeric' };
                dateElement.textContent = now.toLocaleDateString('pt-BR', options);
            }
            
            if (timeElement) {
                timeElement.textContent = now.toLocaleTimeString('pt-BR');
            }
        }
        
        updateDateTime();
        setInterval(updateDateTime, 1000);
    </script>
</body>
</html>
