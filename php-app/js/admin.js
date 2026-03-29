
        // ── API Request Helper (Fixed for GET/POST) ───────────
        async function apiRequest(url, method = 'POST', params = null) {
            const options = {
                method,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            };
            
            // ✅ FIX: Only add body for POST/PUT/PATCH (GET cannot have body)
            if (params && ['POST', 'PUT', 'PATCH'].includes(method.toUpperCase())) {
                options.body = params instanceof FormData ? params : new URLSearchParams(params);
            }
            
            const response = await fetch(url, options);
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.error || 'Request failed');
            return data;
        }
        
        // ── Admin Actions ──────────────────────────────────────
        let pendingBlockUserId = null;
        
        async function toggleBlock(userId, block) {
            if (block) {
                pendingBlockUserId = userId;
                document.getElementById('blockReason').value = '';
                document.getElementById('blockModal').classList.add('active');
            } else {
                try {
                    await apiRequest('admin.php', 'POST', { action: 'toggle_block', user_id: userId, blocked: false });
                    alert('User unblocked successfully');
                    location.reload(); // Simple reload for now
                } catch (err) {
                    alert('Error: ' + err.message);
                }
            }
        }
        
        function closeBlockModal() {
            document.getElementById('blockModal').classList.remove('active');
            pendingBlockUserId = null;
        }
        
        async function confirmBlock() {
            if (!pendingBlockUserId) return;
            const reason = document.getElementById('blockReason').value.trim();
            try {
                await apiRequest('admin.php', 'POST', { 
                    action: 'toggle_block', 
                    user_id: pendingBlockUserId, 
                    blocked: true, 
                    reason: reason || 'No reason provided' 
                });
                alert('User blocked successfully');
                closeBlockModal();
                location.reload();
            } catch (err) {
                alert('Error: ' + err.message);
            }
        }
        
        async function updateRole(userId, newRole) {
            if (!confirm(`Are you sure you want to make this user an ${newRole}?`)) return;
            try {
                await apiRequest('admin.php', 'POST', { action: 'update_role', user_id: userId, role: newRole });
                alert(`Role updated to ${newRole}`);
                location.reload();
            } catch (err) {
                alert('Error: ' + err.message);
            }
        }
        
        async function viewReports(userId) {
            const modal = document.getElementById('reportsModal');
            const body = document.getElementById('reportsModalBody');
            const title = document.getElementById('modalUserTitle');
            
            modal.classList.add('active');
            title.textContent = 'Loading...';
            body.innerHTML = '<div style="text-align:center;padding:2rem;"><div class="loading-spinner"></div><p>Loading reports...</p></div>';
            
            try {
                // ✅ FIX: Pass action in URL query string for GET request
                const data = await apiRequest(`admin.php?action=get_user_reports&user_id=${userId}`, 'GET');
                
                title.textContent = `Reports for ${data.user.username}`;
                
                if (data.analyses.length === 0) {
                    body.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <p>This user has no analysis reports yet.</p>
                        </div>
                    `;
                    return;
                }
                
                let html = `<p style="margin-bottom:1rem;color:var(--text-secondary);">${data.count} report${data.count !== 1 ? 's' : ''} found</p>`;
                
                data.analyses.forEach(analysis => {
                    const result = JSON.parse(analysis.result_json);
                    const date = new Date(analysis.timestamp).toLocaleString();
                    const isUndamaged = analysis.is_undamaged || result.is_undamaged;
                    
                    html += `
                        <div class="report-item">
                            <div class="report-header">
                                <div>
                                    <div class="report-filename">${htmlspecialchars(analysis.original_filename || analysis.filename)}</div>
                                    <div class="report-date">${date}</div>
                                </div>
                                <span class="badge ${isUndamaged ? 'badge-active' : 'badge-blocked'}" style="font-size:0.7rem;">
                                    ${isUndamaged ? '✓ No Damage' : '⚠ Damage Found'}
                                </span>
                            </div>
                            <div class="report-stats">
                                <span><i class="fas fa-search"></i> ${analysis.total_detections} detection${analysis.total_detections !== 1 ? 's' : ''}</span>
                                <span class="report-cost">$${parseFloat(analysis.cost_min).toFixed(0)}–$${parseFloat(analysis.cost_max).toFixed(0)}</span>
                            </div>
                            <a href="result.php?id=${analysis.id}" class="report-link" target="_blank">
                                <i class="fas fa-external-link-alt"></i> View Full Report
                            </a>
                        </div>
                    `;
                });
                
                body.innerHTML = html;
                
            } catch (err) {
                body.innerHTML = `<p style="color:#991b1b;text-align:center;">Error loading reports: ${err.message}</p>`;
            }
        }
        
        function closeReportsModal() {
            document.getElementById('reportsModal').classList.remove('active');
        }
        
        // ── Real-Time Filters (AJAX + Debounce) ───────────────
        let filterTimeout = null;
        
        function getFilterValues() {
            return {
                search: document.getElementById('filterSearch')?.value.trim() || '',
                role: document.getElementById('filterRole')?.value || '',
                blocked: document.getElementById('filterBlocked')?.value || ''
            };
        }
        
        function updateURL(filters) {
            const params = new URLSearchParams();
            if (filters.search) params.set('search', filters.search);
            if (filters.role) params.set('role', filters.role);
            if (filters.blocked) params.set('blocked', filters.blocked);
            const queryString = params.toString();
            const newURL = queryString ? `admin.php?${queryString}` : 'admin.php';
            window.history.replaceState({}, '', newURL);
        }
        
        async function applyFilters() {
            const filters = getFilterValues();
            const loading = document.getElementById('filterLoading');
            const tbody = document.getElementById('usersTableBody');
            
            loading.style.display = 'block';
            
            try {
                // Build query string for GET request
                const params = new URLSearchParams({
                    action: 'filter_users',
                    ...filters
                });
                
                const data = await apiRequest(`admin.php?${params}`, 'GET');
                
                // Update table body
                tbody.innerHTML = data.tableRows;
                
                // Update stats
                document.getElementById('statTotal').textContent = data.stats.total;
                document.getElementById('statAdmins').textContent = data.stats.admins;
                document.getElementById('statBlocked').textContent = data.stats.blocked;
                
                // Update URL for sharing/bookmarking
                updateURL(filters);
                
            } catch (err) {
                console.error('Filter error:', err);
                tbody.innerHTML = `<tr><td colspan="6" class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading users: ${err.message}</p></td></tr>`;
            } finally {
                loading.style.display = 'none';
            }
        }
        
        function setupRealTimeFilters() {
            const searchInput = document.getElementById('filterSearch');
            const roleSelect = document.getElementById('filterRole');
            const blockedSelect = document.getElementById('filterBlocked');
            const applyBtn = document.getElementById('filterApply');
            
            // Debounced search (300ms delay)
            if (searchInput) {
                searchInput.addEventListener('input', () => {
                    clearTimeout(filterTimeout);
                    filterTimeout = setTimeout(applyFilters, 300);
                });
            }
            
            // Instant filter on select change
            if (roleSelect) roleSelect.addEventListener('change', applyFilters);
            if (blockedSelect) blockedSelect.addEventListener('change', applyFilters);
            
            // Apply button (for mobile or explicit trigger)
            if (applyBtn) applyBtn.addEventListener('click', applyFilters);
        }
        
        // Close modals on outside click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('active');
                    pendingBlockUserId = null;
                }
            });
        });
        
        // Escape HTML helper
        function htmlspecialchars(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
        
        document.addEventListener('DOMContentLoaded', () => {
            setupRealTimeFilters();
        });
   