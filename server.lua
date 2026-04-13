local http = require('socket.http')
local ltn12 = require('ltn12')
local mime = require('mime')
local lfs = require('lfs')

-- 配置
local PORT = 8080
local UPLOAD_DIR = 'uploads'

-- 确保上传目录存在
if not lfs.attributes(UPLOAD_DIR) then
    lfs.mkdir(UPLOAD_DIR)
end

-- 辅助函数
local function read_file(filename)
    local file = io.open(filename, 'rb')
    if not file then return nil end
    local content = file:read('*all')
    file:close()
    return content
end

local function write_file(filename, content)
    local file = io.open(filename, 'wb')
    if not file then return false end
    file:write(content)
    file:close()
    return true
end

local function list_files()
    local files = {}
    for file in lfs.dir(UPLOAD_DIR) do
        if file ~= '.' and file ~= '..' then
            local path = UPLOAD_DIR .. '/' .. file
            local attr = lfs.attributes(path)
            if attr and attr.mode == 'file' then
                table.insert(files, {
                    name = file,
                    size = attr.size
                })
            end
        end
    end
    return files
end

-- 解析HTTP请求
local function parse_request(request)
    local method, path, headers = request:match('^(%u+)%s+([^%s]+)%s+HTTP/%d%.%d')
    local body_start = request:find('\r\n\r\n')
    local body = body_start and request:sub(body_start + 4) or ''
    return method, path, body
end

-- 处理文件上传
local function handle_upload(body)
    local boundary = body:match('boundary=(.-)$')
    if not boundary then return false end
    
    local parts = {} 
    local part_start = 1
    
    while true do
        local boundary_pos = body:find('--' .. boundary, part_start)
        if not boundary_pos then break end
        
        local header_start = boundary_pos + #boundary + 2
        local header_end = body:find('\r\n\r\n', header_start)
        if not header_end then break end
        
        local header = body:sub(header_start, header_end - 1)
        local filename = header:match('filename="(.-)"')
        if filename and filename ~= '' then
            local content_start = header_end + 4
            local content_end = body:find('\r\n--' .. boundary, content_start)
            if content_end then
                local content = body:sub(content_start, content_end - 1)
                local filepath = UPLOAD_DIR .. '/' .. filename
                write_file(filepath, content)
            end
        end
        
        part_start = boundary_pos + #boundary + 2
    end
    
    return true
end

-- 构建HTTP响应
local function build_response(status, headers, body)
    local response = string.format('HTTP/1.1 %d OK\r\n', status)
    for key, value in pairs(headers or {}) do
        response = response .. string.format('%s: %s\r\n', key, value)
    end
    response = response .. '\r\n'
    if body then
        response = response .. body
    end
    return response
end

-- 主服务器循环
local function start_server()
    local server = assert(require('socket').bind('*', PORT))
    print('服务器启动在端口 ' .. PORT)
    
    while true do
        local client = server:accept()
        client:settimeout(10)
        
        local request, err = client:receive('*a')
        if request then
            local method, path, body = parse_request(request)
            
            if method == 'GET' then
                if path == '/files' then
                    -- 返回文件列表
                    local files = list_files()
                    local json = '['
                    for i, file in ipairs(files) do
                        json = json .. string.format('{"name":"%s","size":%d}', file.name, file.size)
                        if i < #files then json = json .. ',' end
                    end
                    json = json .. ']'
                    local response = build_response(200, {
                        ['Content-Type'] = 'application/json',
                        ['Access-Control-Allow-Origin'] = '*'
                    }, json)
                    client:send(response)
                elseif path:match('^/download/') then
                    -- 处理文件下载
                    local filename = path:sub(11)
                    local filepath = UPLOAD_DIR .. '/' .. filename
                    local content = read_file(filepath)
                    if content then
                        local response = build_response(200, {
                            ['Content-Type'] = 'application/octet-stream',
                            ['Content-Disposition'] = string.format('attachment; filename="%s"', filename),
                            ['Access-Control-Allow-Origin'] = '*'
                        }, content)
                        client:send(response)
                    else
                        local response = build_response(404, {
                            ['Access-Control-Allow-Origin'] = '*'
                        }, 'File not found')
                        client:send(response)
                    end
                else
                    -- 返回404
                    local response = build_response(404, {
                        ['Access-Control-Allow-Origin'] = '*'
                    }, 'Not found')
                    client:send(response)
                end
            elseif method == 'POST' and path == '/upload' then
                -- 处理文件上传
                local success = handle_upload(body)
                local response = build_response(success and 200 or 400, {
                    ['Access-Control-Allow-Origin'] = '*'
                }, success and 'Upload successful' or 'Upload failed')
                client:send(response)
            elseif method == 'DELETE' and path:match('^/delete/') then
                -- 处理文件删除
                local filename = path:sub(9)
                local filepath = UPLOAD_DIR .. '/' .. filename
                local success = os.remove(filepath)
                local response = build_response(success and 200 or 400, {
                    ['Access-Control-Allow-Origin'] = '*'
                }, success and 'Delete successful' or 'Delete failed')
                client:send(response)
            elseif method == 'OPTIONS' then
                -- 处理CORS预检请求
                local response = build_response(200, {
                    ['Access-Control-Allow-Origin'] = '*',
                    ['Access-Control-Allow-Methods'] = 'GET, POST, DELETE, OPTIONS',
                    ['Access-Control-Allow-Headers'] = 'Content-Type'
                })
                client:send(response)
            else
                -- 返回405
                local response = build_response(405, {
                    ['Access-Control-Allow-Origin'] = '*'
                }, 'Method not allowed')
                client:send(response)
            end
        end
        
        client:close()
    end
end

-- 启动服务器
start_server()