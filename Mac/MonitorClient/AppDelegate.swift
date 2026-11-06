    @objc func TakeScreenShotsAndPost() {
        captureScreenshotAndPost(completion: nil)
    }

    // Manual sends use the same capture path, with an actual upload result.
    // Use a unique filename: periodic and manual captures can overlap.
    private func captureScreenshotAndPost(completion: ((Bool,String)->Void)?) {
        if !Thread.isMainThread {
            DispatchQueue.main.async { [weak self] in
                self?.captureScreenshotAndPost(completion:completion)
            }
            return
        }
        guard isScreenRecordingEnabled() else {
            let reason="Screen Recording permission is missing"
            logError(reason, context:"Screenshot")
            requestScreenRecordingPermission()
            completion?(false,reason)
            return
        }
        Task { [weak self] in
            guard let self=self else { return }
            do {
                let displays=try await SCShareableContent.excludingDesktopWindows(
                    false,onScreenWindowsOnly:true).displays
                guard !displays.isEmpty else {
                    let reason="No displays available to capture"
                    self.logError(reason,context:"Screenshot")
                    completion?(false,reason)
                    return
                }
                let bounds=displays.reduce(CGRect.null) { $0.union($1.frame) }
                let scale=displays.map {
                    CGFloat($0.width)/max(CGFloat(1),$0.frame.width)
                }.max() ?? 1
                let width=Int(ceil(bounds.width*scale))
                let height=Int(ceil(bounds.height*scale))
                guard width>0,height>0,
                      let context=CGContext(data:nil,width:width,height:height,
                           bitsPerComponent:8,bytesPerRow:0,
                           space:CGColorSpaceCreateDeviceRGB(),
                           bitmapInfo:CGImageAlphaInfo.premultipliedLast.rawValue) else {
                    let reason="Screenshot bitmap allocation failed"
                    self.logError(reason,context:"Screenshot")
                    completion?(false,reason)
                    return
                }
                context.scaleBy(x:scale,y:scale)
                for display in displays {
                    let filter=SCContentFilter(display:display,
                        excludingApplications:[],exceptingWindows:[])
                    let configuration=SCStreamConfiguration()
                    configuration.width=display.width
                    configuration.height=display.height
                    configuration.showsCursor=false
                    let image=try await SCScreenshotManager.captureImage(
                        contentFilter:filter,configuration:configuration)
                    context.draw(image,in:CGRect(
                        x:display.frame.minX-bounds.minX,
                        y:bounds.maxY-display.frame.maxY,
                        width:display.frame.width,
                        height:display.frame.height))
                }
                guard let result=context.makeImage(),
                      let jpeg=NSBitmapImageRep(cgImage:result).representation(
                          using:.jpeg,properties:[.compressionFactor:0.55]) else {
                    let reason="Screenshot JPEG encoding failed"
                    self.logError(reason,context:"Screenshot")
                    completion?(false,reason)
                    return
                }
                let filename=NSTemporaryDirectory()+"monitorclient-"+UUID().uuidString+".jpg"
                try jpeg.write(to:URL(fileURLWithPath:filename),options:.atomic)
                self.postImage(path:filename,completion:completion)
            } catch {
                let reason="ScreenCaptureKit error: \(error.localizedDescription)"
                self.logError(reason,context:"Screenshot")
                completion?(false,reason)
            }
        }
    }


